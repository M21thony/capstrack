<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Team Management - CapsTRACK</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/team.css')
</head>
<body>
@include('student.partials.sidebar')
<!-- MAIN -->
<div class="main">
  <div class="topbar">
    <div class="page-title">Team Management</div>
    <div class="topbar-actions">
      <div class="notif-btn">
        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <div class="notif-dot"></div>
      </div>
    </div>
  </div>
  <div class="content">
    @include('student.partials.page-tutorial', ['page' => 'team'])

    @if($myTeam)
    <!-- TEAM DASHBOARD -->
    <div class="card" style="margin-bottom: 20px;">
      <div class="team-dash-header" style="display: flex; align-items: center; gap: 14px;">
        <div class="team-dash-logo" style="width: 56px; height: 56px; border-radius: 14px; background: #fbbf24; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; color: #1a1a1a; flex-shrink: 0;">
          {{ substr($myTeam->team_name, 0, 2) }}
        </div>
        <div style="flex: 1; min-width: 0;">
          <div style="font-size: 20px; font-weight: 700; color: var(--text); letter-spacing: -0.3px;">{{ $myTeam->team_name }}</div>
          <div style="font-size: 12px; color: var(--text3); margin-top: 2px;">{{ auth()->user()->studentSchoolYear()->first()?->program ?? 'BSIT-4A' }} · Capstone AY 2025–2026</div>
        </div>
        <div style="background: #16a34a; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
          <span style="width: 6px; height: 6px; border-radius: 50%; background: #fff; display: inline-block;"></span>ACTIVE
        </div>
      </div>

      <div style="margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <!-- Team Members -->
        <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px;">
          <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 14px;">TEAM MEMBERS</div>
          <div style="display: flex; flex-direction: column; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                  <div style="width: 36px; height: 36px; border-radius: 50%; background: #fbbf24; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #1a1a1a;">{{ auth()->user()->avatarInitials() }}</div>
              <div style="flex: 1;">
                <div style="font-size: 13px; font-weight: 600; color: var(--text);">{{ auth()->user()->f_name }} {{ auth()->user()->l_name }}</div>
                <div style="font-size: 11px; color: var(--text3);">Team Leader</div>
              </div>
              <div style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700;">LEADER</div>
            </div>
            @foreach($myTeam->members as $member)
              @if($member->student_id === auth()->user()->student_id) @continue @endif
              <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff;">
                  {{ strtoupper(substr($member->avatarInitials() ?: ($member->f_name ?? $member->email ?? '?'), 0, 2)) }}
                </div>
                <div style="flex: 1;">
                  <div style="font-size: 13px; font-weight: 600; color: var(--text);">{{ trim(($member->f_name ?? '') . ' ' . ($member->l_name ?? '')) ?: $member->email ?? 'Member' }}</div>
                  <div style="font-size: 11px; color: var(--text3);">{{ $member->l_name ?? '' }}</div>
                </div>
                <div style="background: var(--bg2); color: var(--text3); padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700;">MEMBER</div>
              </div>
            @endforeach
          </div>
          <div style="margin-top: 14px; display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text3);">
            @php
              $total = 5;
              $count = \App\Models\StudentSchoolYear::where('team_id', $myTeam->team_id)->count();
            @endphp
            @for($i = 0; $i < $total; $i++)
              <span style="width: 14px; height: 4px; border-radius: 2px; display: inline-block; background: {{ $i < $count ? '#fbbf24' : 'var(--border)' }};"></span>
            @endfor
            <span style="margin-left: 4px;">{{ $count }} / {{ $total }} members</span>
          </div>
        </div>

        <!-- Team Overview -->
        <div style="display: flex; flex-direction: column; gap: 0;">
          <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px;">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 14px;">TEAM OVERVIEW</div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
              <div style="display: flex; justify-content: space-between; font-size: 12px;">
                <span style="color: var(--text3);">Section</span>
                <span style="font-weight: 700; color: var(--text);">{{ auth()->user()->studentSchoolYear()->first()?->program ?? 'BSIT-4A' }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 12px; border-top: 1px solid var(--border); padding-top: 10px;">
                <span style="color: var(--text3);">Status</span>
                <span style="font-weight: 700; color: #16a34a;">{{ ucfirst($myTeam->team_status ?? 'Active') }}</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 12px; border-top: 1px solid var(--border); padding-top: 10px;">
                <span style="color: var(--text3);">Your Role</span>
                <span style="font-weight: 700; color: #fbbf24;">Leader</span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 12px; border-top: 1px solid var(--border); padding-top: 10px;">
                <span style="color: var(--text3);">Date Formed</span>
                <span style="font-weight: 700; color: var(--text);">{{ \Carbon\Carbon::now()->format('F j, Y') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div style="margin-top: 18px; text-align: right; padding-top: 10px; border-top: 1px solid var(--border);">
        <form method="POST" action="{{ route('team.destroy') }}" onsubmit="return confirm('Are you sure you want to leave this team? This action cannot be undone.')" style="display: inline;">
          @csrf
          @method('DELETE')
          <button type="submit" style="padding: 5px 10px; border-radius: 6px; border: 1px solid #fecaca; background: #fef2f2; color: #ef4444; font-size: 10px; font-weight: 700; cursor: pointer;">Leave Team</button>
        </form>
      </div>
    </div>
    @else
    <!-- CREATE TEAM SECTION -->
    <div class="card" style="margin-bottom: 20px;">
      <div class="section-title">Create New Team</div>
      <form id="create-team-form" method="POST" action="{{ route('team.store') }}" style="margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
        @csrf
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <label for="team_name" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3);">Team Name <span style="color:#ef4444">*</span></label>
          <input type="text" id="team_name" name="team_name" class="custom-input" placeholder="e.g. Capstone Warriors" required>
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <label for="project_title" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3);">Project Title</label>
          <input type="text" id="project_title" name="project_title" class="custom-input" placeholder="e.g. Smart Attendance System">
        </div>
        <div style="display: flex; flex-direction: column; gap: 6px;">
          <label for="team_status" style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3);">Team Status</label>
          <select id="team_status" name="team_status" class="custom-select">
            <option value="Active">Active</option>
            <option value="Full">Full</option>
            <option value="Need Members">Need Members</option>
            <option value="Done">Done</option>
          </select>
        </div>
        <div style="display: flex; align-items: flex-end;">
          <button type="submit" class="btn-primary" style="width: 100%;">Create Team</button>
        </div>
      </form>
    </div>
    @endif

    @if($errors->any())
      <div class="alert" style="background: #fef2f2; border: 1px solid #fecaca; color: #ef4444; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
        @foreach($errors->all() as $error)
          <div>{{ $error }}</div>
        @endforeach
      </div>
    @endif

    @if(session('status'))
      <div class="alert" style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px;">
        {{ session('status') }}
      </div>
    @endif

    <!-- SEARCH AND FILTERS -->
    <main class="main-content">
      <div class="page-header">
        <h1 style="font-size: 24px; font-weight: 700; color: var(--text);">TEAM SELECTION</h1>
      </div>

  <div class="team-container">
    <!-- LEFT COLUMN -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
      <div class="card" style="border-style: dashed; border-width: 2px; cursor: pointer;" onclick="openPostModal(@json($myRecruitment ?? null))">
        <div style="display: flex; gap: 12px; align-items: center;">
          <div class="avatar" style="width: 40px; height: 40px; background: var(--accent); color: white;">+</div>
          <div style="color: var(--text3); font-size: 14px;">Looking for a team or members? Post your expertise here...</div>
        </div>
      </div>

      <div class="profile-grid" id="recruitment-feed">
          @if($myRecruitment)
            @php
              $isLeader = $myRecruitment->recruitment_goal === 'build_a_team';
              $goalLabel = $isLeader ? 'BUILD A TEAM' : 'Find a Team';
              $ringClass = $isLeader ? 'hiring' : 'looking';
              $dotColor = $isLeader ? '#22c55e' : '#3b82f6';
              $initials = auth()->user()->avatarInitials();
            @endphp
            <div class="profile-card-compact" id="my-active-post"
              onclick='viewPostDetails(
                this,
                @json(auth()->user()->f_name . " " . auth()->user()->l_name),
                @json(implode(", ", $skills ?? [])),
                @json($myRecruitment->looking_for ?? ""),
                @json(auth()->user()->team->team_name ?? "No Team"),
                @json(auth()->user()->team->project_title ?? "No project set"),
                @json(auth()->user()->student_id),
                true,
                @json($isLeader ? "Leader" : "Individual"),
                @json($myRecruitment)
              )'>
              <div class="post-actions" style="position:absolute; top:8px; right:8px; z-index:2;">
                <form method="POST" action="{{ route('recruitment.destroy', ['recruitment' => $myRecruitment->recruitment_id]) }}" id="delete-recruitment-form" style="display:inline;">
                  @csrf
                  @method('DELETE')
                  <button type="button" class="btn-icon" style="width:24px; height:24px;" onclick="event.stopPropagation(); openDeleteModal()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                  </button>
                </form>
              </div>
              <div class="avatar-ring {{ $ringClass }}">
                <div class="avatar-text">{{ $initials }}</div>
                <div class="status-badge-dot" style="background:{{ $dotColor }}"></div>
              </div>
              <div class="name-label">{{ auth()->user()->f_name }} {{ auth()->user()->l_name }}</div>
              <div class="goal-label">{{ $goalLabel }}</div>
            </div>
          @endif

          @forelse($otherRecruitments as $recruit)
            @php
              $isLeader = $recruit->recruitment_goal === 'build_a_team';
              $goalLabel = $isLeader ? 'BUILD A TEAM' : 'Find a Team';
              $ringClass = $isLeader ? 'hiring' : 'looking';
              $dotColor = $isLeader ? '#22c55e' : '#3b82f6';
              $initials = $recruit->student->avatarInitials();
            @endphp
            <div class="profile-card-compact"
              onclick='viewPostDetails(
                this,
                @json($recruit->student->f_name . " " . $recruit->student->l_name),
                @json(implode(", ", $recruit->roles ?? [])),
                @json($recruit->looking_for ?? ""),
                @json($recruit->student->team->team_name ?? "No Team"),
                @json($recruit->student->team->project_title ?? "No project set"),
                @json($recruit->student->student_id),
                false,
                @json($isLeader ? "Leader" : "Individual"),
                null
              )'>
              <div class="avatar-ring {{ $ringClass }}">
                <div class="avatar-text">{{ $initials }}</div>
                <div class="status-badge-dot" style="background:{{ $dotColor }}"></div>
              </div>
              <div class="name-label">{{ $recruit->student->f_name }} {{ $recruit->student->l_name }}</div>
              <div class="goal-label">{{ $goalLabel }}</div>
            </div>
          @empty
            @if(!$myRecruitment)
              <div style="grid-column: 1 / -1; text-align: center; padding: 30px; color: var(--text3); font-size: 13px;">
                No recruitment posts yet. Be the first to post!
              </div>
            @endif
          @endforelse
      </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
      <div class="card">
        <div class="section-title">Section Overview</div>
        <div style="margin-top: 15px;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            <span style="font-size: 12px; color: var(--text3);">Total Students</span>
            <span style="font-size: 12px; font-weight: 700;">45</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            <span style="font-size: 12px; color: var(--text3);">Teamed Up</span>
            <span style="font-size: 12px; font-weight: 700; color: var(--green);">32</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span style="font-size: 12px; color: var(--text3);">Still Individual</span>
            <span style="font-size: 12px; font-weight: 700; color: var(--amber);">13</span>
          </div>
        </div>
      </div>
      <div class="card">
    <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
      Team Invites
      <span style="background: var(--accent); color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px;">2 New</span>
    </div>
    
    <div style="margin-top: 15px;">
      <div class="invitation-card">
        <div style="display: flex; gap: 10px; align-items: center;">
          <div class="avatar" style="width: 32px; height: 32px; font-size: 11px;">TN</div>
          <div>
            <div style="font-size: 12px; font-weight: 600;">Team Nexus</div>
            <div style="font-size: 10px; color: var(--text3);">Sent by Mark Anthony</div>
          </div>
        </div>
        <div class="invitation-actions">
          <button class="btn-accept" onclick="confirmJoin('Team Nexus')">Accept Join</button>
          <button class="btn-decline" title="Decline">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>
      </div>

      <div class="invitation-card">
        <div style="display: flex; gap: 10px; align-items: center;">
          <div class="avatar" style="width: 32px; height: 32px; font-size: 11px; background: var(--bg4);">CD</div>
          <div>
            <div style="font-size: 12px; font-weight: 600;">Code Drifters</div>
            <div style="font-size: 10px; color: var(--text3);">Sent by Alexa Nicole</div>
          </div>
        </div>
        <div class="invitation-actions">
          <button class="btn-accept" onclick="confirmJoin('Code Drifters')">Accept Join</button>
          <button class="btn-decline" title="Decline">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
          </button>
        </div>
      </div>
    </div>
    </div>
  </div>
  </div>
</main>

  <div class="pagination-container">
      <div class="pg-arrow" title="Previous">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      </div>
      <div class="pg-btn active">1</div>
      <div class="pg-btn">2</div>
      <div class="pg-btn">3</div>
      <div style="color: var(--text3); font-size: 11px; margin: 0 4px;">...</div>
      <div class="pg-btn">12</div>
      <div class="pg-arrow" title="Next">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
      </div>
  </div>

  <div class="tutorial-note">
      <div class="tutorial-item">
          <div class="dot-guide" style="background: #22c55e; box-shadow: 0 0 8px rgba(34,197,94,0.4);"></div>
          <span>Find Members</span>
      </div>
      <div class="tutorial-item">
          <div class="dot-guide" style="background: #3b82f6; box-shadow: 0 0 8px rgba(59,130,246,0.4);"></div>
          <span>Find Team</span>
      </div>
  </div>

  <div class="legal-credits">
      <div class="credits-title">System Developers</div>
      <div class="credits-names">Jolina · John Kenneth · Mark Anthony · Ian Cyrus · Alexa Nicole</div>
  </div>

  <!-- DETAIL MODAL -->
  <div class="modal-overlay" id="detailModal" onclick="if(event.target==this) this.classList.remove('active')">
      <div class="card" style="width: 380px; padding: 28px; position: relative;">
          <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 18px;">
              <div id="detail-avatar" class="avatar" style="width: 52px; height: 52px; font-size: 18px; flex-shrink: 0;"></div>
              <div style="flex: 1; min-width: 0;">
                  <h3 id="detail-name" style="margin: 0; font-size: 16px; font-weight: 700; color: var(--text);"></h3>
                  <p id="detail-student-id" style="margin: 2px 0 0; font-size: 11px; color: var(--text3);"></p>
              </div>
          </div>

          <p id="detail-owner-msg" style="font-size: 12px; font-weight: 700; color: var(--accent); margin: 0 0 14px; display: none;">Your Post</p>

          <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 12px 14px; margin-bottom: 14px; display: flex; flex-direction: column; gap: 6px;">
              <div style="display: flex; justify-content: space-between; font-size: 12px;">
                  <span style="color: var(--text3);">Looking For</span>
                  <span id="detail-looking-for" style="font-weight: 600; color: var(--text); text-align: right; max-width: 60%;"></span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 12px; border-top: 1px solid var(--border); padding-top: 6px; margin-top: 2px;">
                  <span style="color: var(--text3);">Team</span>
                  <span id="detail-team-name" style="font-weight: 600; color: var(--text); text-align: right; max-width: 60%;"></span>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 12px; border-top: 1px solid var(--border); padding-top: 6px; margin-top: 2px;">
                  <span style="color: var(--text3);">Project</span>
                  <span id="detail-project" style="font-weight: 600; color: var(--text); text-align: right; max-width: 60%;"></span>
              </div>
          </div>

          <div style="margin-bottom: 18px;">
              <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px; letter-spacing: 0.5px;">Expertise</div>
              <div id="detail-skills" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
          </div>

          <button class="btn-primary" style="width: 100%;" id="detail-action-btn">Send Request</button>
      </div>
  </div>

  <!-- POST / EDIT MODAL -->
  <div class="modal-overlay" id="postModal" onclick="if(event.target==this) closePostModal()">
    <div class="card" style="width: 480px; padding: 30px; border: 1px solid var(--accent);">
      <div class="section-title" id="modal-title">Build a Team</div>
      
      <div style="margin-top: 20px;">
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Recruitment Goal</label>
        <select class="custom-select" id="post-goal" style="margin-bottom: 20px;" onchange="updateModalUI()">
          <option value="leader">Build a Team (Find Members)</option>
          <option value="individual">Find a Team (Join Others)</option>
        </select>

         <div class="modal-tutorial" id="modal-note-box">
             <p id="modal-note-text"><strong>CapsTip:</strong> A standard capstone team usually consists of 3-5 members. Setting this helps students know if you still have slots available.</p>
         </div>

         <div id="leader-fields">
          <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Looking For</label>
          <textarea id="leader-looking-for" name="looking_for" rows="3" placeholder="e.g. 1 Backend Dev, 1 UI Designer..." style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 13px; resize: vertical; margin-bottom: 16px;"></textarea>
        </div>

        <div id="individual-fields" style="display:none;">
          <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Short Introduction</label>
          <textarea id="individual-intro" rows="3" placeholder="Briefly introduce yourself and what you bring to the team..." style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 13px; resize: vertical; margin-bottom: 16px;"></textarea>
        </div>

        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Expertise & Roles</label>
        <div class="tag-input-field" id="tag-container" style="margin-bottom: 12px;">
          <span style="color: var(--text3); font-size: 12px;" id="placeholder-text">Click tags below to add...</span>
        </div>

        <div id="skill-selector" style="margin-bottom: 25px;">
          <span class="skill-chip leader-skill" onclick="toggleTag(this, 'UI Designer')">UI Designer</span>
          <span class="skill-chip leader-skill" onclick="toggleTag(this, 'App Developer')">App Developer</span>
          <span class="skill-chip leader-skill" onclick="toggleTag(this, 'Backend Dev')">Backend Dev</span>
          <span class="skill-chip leader-skill" onclick="toggleTag(this, 'Documentation')">Documentation</span>
          <span class="skill-chip individual-skill" style="display:none;" onclick="toggleTag(this, 'Leadership')">Leadership</span>
          <span class="skill-chip individual-skill" style="display:none;" onclick="toggleTag(this, 'Research')">Research</span>
          <span class="skill-chip individual-skill" style="display:none;" onclick="toggleTag(this, 'Design')">Design</span>
          <span class="skill-chip individual-skill" style="display:none;" onclick="toggleTag(this, 'Testing')">Testing</span>
        </div>
        
        <div style="display: flex; gap: 10px;">
          <form method="POST" action="{{ route('recruitment.store') }}" style="flex: 1;" id="recruitment-form" onsubmit="publishPost(event)">
            @csrf
            <input type="hidden" name="_method" id="form-method-input" value="POST">
            <input type="hidden" name="recruitment_goal" id="recruitment-goal-input" value="build_a_team">
            <input type="hidden" name="recruitment_id" id="recruitment-id-input" value="">
            <input type="hidden" name="looking_for" id="looking-for-input" value="">
            <input type="hidden" name="introduction" id="introduction-input" value="">
            <button type="submit" class="btn-primary" style="width: 100%;" id="submit-btn-text">Publish Post</button>
          </form>
          <button class="google-btn" style="flex: 1; border: none;" onclick="closePostModal()">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- DELETE CONFIRM MODAL -->
  <div class="modal-overlay" id="deleteModal" onclick="if(event.target==this) closeDeleteModal()">
    <div class="card" style="width: 360px; padding: 30px; text-align: center;">
      <div class="section-title">Delete Post</div>
      <p style="margin-top: 12px; font-size: 13px; color: var(--text2);">Are you sure you want to remove your recruitment post? This action cannot be undone.</p>
      <div style="display: flex; gap: 10px; margin-top: 20px;">
        <button class="btn-primary" style="flex: 1; background: #ef4444;" onclick="submitDeleteForm()">Delete</button>
        <button class="google-btn" style="flex: 1; border: none;" onclick="closeDeleteModal()">Cancel</button>
      </div>
    </div>
  </div>

<script>
let selectedTags = new Set();
// Store the update URL template from Laravel
const updateUrlTemplate = '{{ route('recruitment.update', ['recruitment' => ':id']) }}';

function toggleTag(chip, skill) {
    if (selectedTags.has(skill)) {
        selectedTags.delete(skill);
        chip.classList.remove('selected');
    } else {
        selectedTags.add(skill);
        chip.classList.add('selected');
    }
    renderTags();
}

function renderTags() {
    const container = document.getElementById('tag-container');
    if (selectedTags.size > 0) {
        container.innerHTML = Array.from(selectedTags).map(function(tag) {
            return '<div class="selected-tag">' + tag + '</div>';
        }).join('');
    } else {
        container.innerHTML = '<span style="color: var(--text3); font-size: 12px;">Click tags below to add...</span>';
    }
}

function publishPost(event) {
    event.preventDefault();

    if (selectedTags.size === 0) {
        alert('Please select at least one expertise.');
        return;
    }

    document.getElementById('looking-for-input').value = document.getElementById('leader-looking-for').value;
    document.getElementById('introduction-input').value = document.getElementById('individual-intro').value;

    const form = document.getElementById('recruitment-form');

    // Remove any previously appended role inputs
    form.querySelectorAll('input[name="roles[]"]').forEach(function(el) { el.remove(); });

    // Append each selected tag as a hidden input
    Array.from(selectedTags).forEach(function(tag) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'roles[]';
        input.value = tag;
        form.appendChild(input);
    });

    const recruitmentId = document.getElementById('recruitment-id-input').value;
    if (recruitmentId) {
        // UPDATE: change form action and spoof PUT
        form.action = updateUrlTemplate.replace(':id', recruitmentId);
        document.getElementById('form-method-input').value = 'PUT';
    } else {
        // CREATE: reset to store route and POST
        form.action = '{{ route('recruitment.store') }}';
        document.getElementById('form-method-input').value = 'POST';
    }

    form.submit();
}

function openPostModal(recruitment) {
    // Reset state
    selectedTags.clear();
    document.querySelectorAll('.skill-chip').forEach(function(chip) { chip.classList.remove('selected'); });
    document.getElementById('leader-looking-for').value = '';
    document.getElementById('individual-intro').value = '';
    document.getElementById('recruitment-id-input').value = '';
    document.getElementById('form-method-input').value = 'POST';
    document.getElementById('submit-btn-text').textContent = 'Publish Post';
    document.getElementById('post-goal').value = 'leader';

    if (recruitment && recruitment.recruitment_id) {
        // Editing an existing post
        document.getElementById('recruitment-id-input').value = recruitment.recruitment_id;
        document.getElementById('submit-btn-text').textContent = 'Update Post';

        const isLeader = recruitment.recruitment_goal === 'build_a_team';
        document.getElementById('post-goal').value = isLeader ? 'leader' : 'individual';

        if (isLeader) {
            document.getElementById('leader-looking-for').value = recruitment.looking_for || '';
        } else {
            document.getElementById('individual-intro').value = recruitment.introduction || '';
        }

        // Pre-select saved roles
        if (Array.isArray(recruitment.roles)) {
            recruitment.roles.forEach(function(role) {
                selectedTags.add(role);
                document.querySelectorAll('.skill-chip').forEach(function(chip) {
                    if (chip.textContent.trim() === role) {
                        chip.classList.add('selected');
                    }
                });
            });
        }
    }

    updateModalUI();
    renderTags();
    document.getElementById('postModal').classList.add('active');
}

function closePostModal() {
    document.getElementById('postModal').classList.remove('active');
}

function viewPostDetails(cardEl, name, skills, lookingFor, teamName, projectTitle, studentId, isOwner, type, recruitment) {
    const modal = document.getElementById('detailModal');

    document.getElementById('detail-name').textContent = name;
    document.getElementById('detail-student-id').textContent = 'Student ID: ' + studentId;
    document.getElementById('detail-looking-for').textContent = lookingFor ? lookingFor : '-';
    document.getElementById('detail-team-name').textContent = teamName || '-';
    document.getElementById('detail-project').textContent = projectTitle || '-';

    const initials = name.split(' ').map(function(n) { return n[0]; }).join('').substring(0, 2).toUpperCase();
    document.getElementById('detail-avatar').textContent = initials;

    const skillContainer = document.getElementById('detail-skills');
    skillContainer.innerHTML = skills.split(',').map(function(s) {
        return '<span class="expertise-tag" style="margin:0; font-size:10px">' + s.trim() + '</span>';
    }).join('');

    const ownerMsg = document.getElementById('detail-owner-msg');
    const actionBtn = document.getElementById('detail-action-btn');

    if (isOwner) {
        ownerMsg.style.display = 'block';
        actionBtn.textContent = 'Edit Post';
        actionBtn.onclick = function() {
            modal.classList.remove('active');
            openPostModal(recruitment);
        };
    } else {
        ownerMsg.style.display = 'none';
        actionBtn.textContent = 'Send Request';
        actionBtn.onclick = null;
    }

    modal.classList.add('active');
}

function updateModalUI() {
    const goal = document.getElementById('post-goal').value;
    const noteText = document.getElementById('modal-note-text');
    const noteBox = document.getElementById('modal-note-box');
    const leaderFields = document.getElementById('leader-fields');
    const individualFields = document.getElementById('individual-fields');
    const modalTitle = document.getElementById('modal-title');
    const leaderSkills = document.querySelectorAll('.leader-skill');
    const individualSkills = document.querySelectorAll('.individual-skill');

    if (goal === 'leader') {
        leaderFields.style.display = 'block';
        individualFields.style.display = 'none';
        modalTitle.textContent = 'Build a Team';
        noteText.innerHTML = '<strong>CapsTip:</strong> Clearly state the roles you need (e.g. 1 Backend, 1 UI) to attract the right members for your 5-person team.';
        noteBox.style.borderLeftColor = 'var(--accent)';
        leaderSkills.forEach(function(chip) { chip.style.display = 'inline-block'; });
        individualSkills.forEach(function(chip) { chip.style.display = 'none'; });
        document.getElementById('recruitment-goal-input').value = 'build_a_team';
    } else {
        leaderFields.style.display = 'none';
        individualFields.style.display = 'block';
        modalTitle.textContent = 'Find a Team';
        noteText.innerHTML = '<strong>CapsTip:</strong> Highlight your best skills! Other team leaders will see your profile and invite you if your expertise matches their project needs.';
        noteBox.style.borderLeftColor = '#3b82f6';
        leaderSkills.forEach(function(chip) { chip.style.display = 'none'; });
        individualSkills.forEach(function(chip) { chip.style.display = 'inline-block'; });
        document.getElementById('recruitment-goal-input').value = 'find_a_team';
    }
}

function confirmJoin(teamName) {
    if (confirm('Are you sure you want to join ' + teamName + '? This will close your current recruitment post.')) {
        alert('Welcome to ' + teamName + '! Redirecting to Team Dashboard...');
    }
}

function openDeleteModal() {
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

function submitDeleteForm() {
    document.getElementById('delete-recruitment-form').submit();
}

document.addEventListener('DOMContentLoaded', function() {
    updateModalUI();
});
</script>
</body>
</html>