<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Team Management - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet" />
@vite('resources/student-ui/Styles/team.css')
</head>
<style>
  .pm-close-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; padding: 0; background-color: var(--bg2); color: var(--text3); border: none; border-radius: 50%; cursor: pointer; transition: all 0.15s ease-in-out; outline: none; }
  .pm-close-btn:hover { background-color: var(--border); color: var(--text); transform: scale(1.05); }
  .tag-input-field { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); min-height: 42px; box-sizing: border-box; cursor: text; }
  #tag-search-input { border: none !important; background: transparent !important; color: var(--text) !important; font-size: 13px !important; outline: none !important; flex: 1 !important; min-width: 60px !important; padding: 4px 0 !important; }
  .custom-tag-chip { display: inline-flex; align-items: center; gap: 6px; background: var(--accent); color: #ffffff; font-size: 12px; font-weight: 500; padding: 4px 10px; border-radius: 6px; animation: tagFadeIn 0.15s ease-in-out; }
  .custom-tag-chip-remove { display: inline-flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.2); border: none; color: #ffffff; border-radius: 50%; width: 14px; height: 14px; cursor: pointer; font-size: 10px; padding: 0; transition: background 0.1s; }
  .custom-tag-chip-remove:hover { background: rgba(255,255,255,0.4); }
  .tag-suggestions-box { position: absolute; top: 100%; left: 0; width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; max-height: 160px; overflow-y: auto; z-index: 10; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-top: 4px; }
  .tag-suggestion-item { padding: 10px 14px; font-size: 13px; color: var(--text); cursor: pointer; transition: background 0.1s; }
  .tag-suggestion-item:hover { background: var(--bg2); color: var(--accent); }
  @keyframes tagFadeIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
</style>
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
              <div style="width: 36px; height: 36px; border-radius: 50%; background: #fbbf24; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #1a1a1a; overflow: hidden; flex-shrink: 0;">
                @if (auth()->user()->avatarUrl())
                  <img src="{{ auth()->user()->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                @else
                  {{ auth()->user()->avatarInitials() }}
                @endif
              </div>
              <div style="flex: 1;">
                <div style="font-size: 13px; font-weight: 600; color: var(--text);">{{ auth()->user()->f_name }} {{ auth()->user()->l_name }}</div>
                <div style="font-size: 11px; color: var(--text3);">Team Leader</div>
              </div>
              <div style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700;">LEADER</div>
            </div>
            @foreach($myTeam->members as $member)
              @if($member->student_id === auth()->user()->student_id) @continue @endif
              <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 36px; height: 36px; border-radius: 50%; background: #7c3aed; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #fff; overflow: hidden; flex-shrink: 0;">
                  @if ($member->avatarUrl())
                    <img src="{{ $member->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                  @else
                    {{ strtoupper(substr($member->avatarInitials() ?: ($member->f_name ?? $member->email ?? '?'), 0, 2)) }}
                  @endif
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
                @php
                  $authStudent = auth()->user();
                  $program = $authStudent->studentSchoolYear()->first()?->program ?? '';
                  $yearSection = $authStudent->year_section ?? '';
                  // year_section format: "3rd · B"  — split on " · "
                  $parts = array_map('trim', explode('·', $yearSection));
                  $year = $parts[0] ?? '';
                  $section = $parts[1] ?? '';
                  $sectionLabel = $program
                    ? $program . ($year ? ', ' . $year : '') . ($section ? ' - ' . $section : '')
                    : $yearSection;
                @endphp
                <span style="font-weight: 700; color: var(--text);">{{ $sectionLabel }}</span>
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
                <span style="font-weight: 700; color: var(--text);">{{ $myTeam->created_at ? \Carbon\Carbon::parse($myTeam->created_at)->format('F j, Y') : \Carbon\Carbon::now()->format('F j, Y') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div style="margin-top: 18px; text-align: right; padding-top: 10px; border-top: 1px solid var(--border);">
        <form method="POST" action="{{ route('team.destroy') }}" id="leave-team-form" style="display: inline;">
          @csrf
          @method('DELETE')
          <button type="button" onclick="confirmLeaveTeam()" style="padding: 5px 10px; border-radius: 6px; border: 1px solid #fecaca; background: #fef2f2; color: #ef4444; font-size: 10px; font-weight: 700; cursor: pointer;">Leave Team</button>
        </form>
      </div>
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

      @if(!$myTeam)
      <div class="card" style="border-style: dashed; border-width: 2px; cursor: pointer;" onclick="openPostModal()">

      @if(!$myTeam)
      <div class="card" style="border-style: dashed; border-width: 2px; cursor: pointer;" onclick="openPostModal()">

        <div style="display: flex; gap: 12px; align-items: center;">
          <div class="avatar" style="width: 40px; height: 40px; background: var(--accent); color: white;">+</div>
          <div style="color: var(--text3); font-size: 14px;">Looking for a team or members? Post your expertise here...</div>
        </div>
      </div>
      @endif

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
            <div class="profile-card-compact" id="my-active-post" onclick='viewPostDetails(this, "{{ addslashes(auth()->user()->f_name . " " . auth()->user()->l_name) }}", "{{ implode(", ", $skills) }}", "{{ $myRecruitment->looking_for }}", "{{ auth()->user()->team->team_name ?? 'No Team' }}", "{{ auth()->user()->team->project_title ?? 'No project set' }}", "{{ auth()->user()->student_number }}", true, "{{ $isLeader ? 'Leader' : 'Individual' }}", {{ auth()->user()->student_id }}, {{ auth()->user()->team->team_id ?? 'null' }}, "{{ auth()->user()->avatarUrl() }}")'>

            <div class="profile-card-compact" id="my-active-post" onclick='viewPostDetails(this, "{{ addslashes(auth()->user()->f_name . " " . auth()->user()->l_name) }}", "{{ implode(", ", $skills) }}", "{{ $myRecruitment->looking_for }}", "{{ auth()->user()->team->team_name ?? 'No Team' }}", "{{ auth()->user()->team->project_title ?? 'No project set' }}", "{{ auth()->user()->student_number }}", true, "{{ $isLeader ? 'Leader' : 'Individual' }}", {{ auth()->user()->student_id }}, {{ auth()->user()->team->team_id ?? 'null' }}, "{{ auth()->user()->avatarUrl() }}")'>

              <div class="post-actions" style="position:absolute; top:8px; right:8px; z-index:2;">
                <form method="POST" action="{{ route('recruitment.destroy', $myRecruitment) }}" id="delete-recruitment-form" style="display:inline;">
                  @csrf
                  @method('DELETE')
                  <button type="button" class="btn-icon" style="width:24px; height:24px;" onclick="event.stopPropagation(); openDeleteModal()"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4 a2 2 0 0 1 2 2v2"></path></svg></button>
                </form>
              </div>
              <div class="avatar-ring {{ $ringClass }}">
                @if (auth()->user()->avatarUrl())
                  <img src="{{ auth()->user()->avatarUrl() }}" alt="">
                @else
                  <div class="avatar-text">{{ $initials }}</div>
                @endif
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
            <div class="profile-card-compact" onclick='viewPostDetails(this, "{{ addslashes($recruit->student->f_name . " " . $recruit->student->l_name) }}", "{{ implode(", ", $recruit->roles ?? []) }}", "{{ $recruit->looking_for }}", "{{ $recruit->student->team->team_name ?? 'No Team' }}", "{{ $recruit->student->team->project_title ?? 'No project set' }}", "{{ $recruit->student->student_number }}", false, "{{ $isLeader ? 'Leader' : 'Individual' }}", {{ $recruit->student_id }}, {{ $recruit->student->team->team_id ?? 'null' }}, "{{ $recruit->student->avatarUrl() }}")'>

            <div class="profile-card-compact" onclick='viewPostDetails(this, "{{ addslashes($recruit->student->f_name . " " . $recruit->student->l_name) }}", "{{ implode(", ", $recruit->roles ?? []) }}", "{{ $recruit->looking_for }}", "{{ $recruit->student->team->team_name ?? 'No Team' }}", "{{ $recruit->student->team->project_title ?? 'No project set' }}", "{{ $recruit->student->student_number }}", false, "{{ $isLeader ? 'Leader' : 'Individual' }}", {{ $recruit->student_id }}, {{ $recruit->student->team->team_id ?? 'null' }}, "{{ $recruit->student->avatarUrl() }}")'>

              <div class="avatar-ring {{ $ringClass }}">
                @if ($recruit->student->avatarUrl())
                  <img src="{{ $recruit->student->avatarUrl() }}" alt="">
                @else
                  <div class="avatar-text">{{ $initials }}</div>
                @endif
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
            <span style="font-size: 12px; font-weight: 700;">{{ $totalStudents }}</span>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 12px; border-bottom: 1px solid var(--border); padding-bottom: 8px;">
            <span style="font-size: 12px; color: var(--text3);">Teamed Up</span>
            <span style="font-size: 12px; font-weight: 700; color: var(--green);">{{ $teamedUp }}</span>
          </div>
          <div style="display: flex; justify-content: space-between;">
            <span style="font-size: 12px; color: var(--text3);">Still Individual</span>
            <span style="font-size: 12px; font-weight: 700; color: var(--amber);">{{ $stillIndividual }}</span>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
          Team Invites
          @if($invites->count() > 0)
            <span style="background: var(--accent); color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px;">{{ $invites->count() }} New</span>
          @endif
        </div>
        
        <div style="margin-top: 15px;">
          @forelse($invites as $invite)
            <div class="invitation-card">
              <div style="display: flex; gap: 10px; align-items: center;">
                <div class="avatar" style="width: 32px; height: 32px; font-size: 11px; overflow: hidden; flex-shrink: 0;">
                  @if ($invite->sender->avatarUrl())
                    <img src="{{ $invite->sender->avatarUrl() }}" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                  @else
                    {{ strtoupper(substr($invite->sender->f_name ?? 'U', 0, 1) . substr($invite->sender->l_name ?? '', 0, 1)) }}
                  @endif
                </div>
                <div>
                  @if($invite->type === 'invite')
                    <div style="font-size: 12px; font-weight: 600;">{{ $invite->team->team_name ?? 'A Team' }} invited you</div>
                    <div style="font-size: 10px; color: var(--text3);">From {{ $invite->sender->f_name ?? '' }} {{ $invite->sender->l_name ?? '' }}</div>
                  @else
                    <div style="font-size: 12px; font-weight: 600;">{{ $invite->sender->f_name ?? 'Someone' }} {{ $invite->sender->l_name ?? '' }} wants to join</div>
                    <div style="font-size: 10px; color: var(--text3);">Requested to join your team</div>
                  @endif
                </div>
              </div>
              <div class="invitation-actions">
                <form method="POST" action="{{ route('invitations.accept', $invite->id) }}" id="accept-form-{{ $invite->id }}" style="display:inline;">
                  @csrf
                  @if($invite->type === 'invite')
                    <button class="btn-accept" type="button" style="cursor:pointer; border:none; outline:none;"
                      onclick="showConfirm({title:'Join Team',message:'Accept invitation from <strong>{{ addslashes($invite->team->team_name ?? 'this team') }}</strong>? Your current post will be removed.',icon:'team',confirmText:'Accept',confirmClass:'success',onConfirm:function(){document.getElementById('accept-form-{{ $invite->id }}').submit();}})">
                      Accept
                    </button>
                  @else
                    <button class="btn-accept" type="button" style="cursor:pointer; border:none; outline:none;"
                      onclick="showConfirm({title:'Accept Request',message:'Let <strong>{{ addslashes(($invite->sender->f_name ?? '') . ' ' . ($invite->sender->l_name ?? '')) }}</strong> join your team? Their post will be removed.',icon:'team',confirmText:'Accept',confirmClass:'success',onConfirm:function(){document.getElementById('accept-form-{{ $invite->id }}').submit();}})">
                      Accept Request
                    </button>
                  @endif
                </form>
                <form method="POST" action="{{ route('invitations.decline', $invite->id) }}" style="display:inline;">
                  @csrf
                  <button class="btn-decline" title="Decline" type="submit">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                  </button>
                </form>
              </div>
            </div>
          @empty
            <div style="text-align: center; padding: 20px; color: var(--text3); font-size: 12px;">
              No pending invites.
            </div>
          @endforelse
        </div>
      </div>
  </div>
  </div>
</main>

  @if ($otherRecruitments->hasPages())
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
      @if ($otherRecruitments->onFirstPage())
          <div class="pg-arrow disabled" title="Previous" style="opacity: 0.5; cursor: not-allowed; display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </div>
      @else
          <a href="{{ $otherRecruitments->previousPageUrl() }}" class="pg-arrow" title="Previous" style="color: inherit; text-decoration: none; display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          </a>
      @endif
      
      @foreach ($otherRecruitments->getUrlRange(1, $otherRecruitments->lastPage()) as $page => $url)
          @if ($page == $otherRecruitments->currentPage())
              <div class="pg-btn active">{{ $page }}</div>
          @else
              <a href="{{ $url }}" class="pg-btn" style="color: inherit; text-decoration: none;">{{ $page }}</a>
          @endif
      @endforeach

      @if ($otherRecruitments->hasMorePages())
          <a href="{{ $otherRecruitments->nextPageUrl() }}" class="pg-arrow" title="Next" style="color: inherit; text-decoration: none; display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
      @else
          <div class="pg-arrow disabled" title="Next" style="opacity: 0.5; cursor: not-allowed; display: flex; align-items: center; justify-content: center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </div>
      @endif
  </div>
  @endif

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
          </div>

          <div style="margin-bottom: 18px;">
              <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px; letter-spacing: 0.5px;">Expertise</div>
              <div id="detail-skills" style="display: flex; flex-wrap: wrap; gap: 6px;"></div>
          </div>

          <button class="btn-primary" style="width: 100%;" id="action-btn-text">Send Request</button>
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
<div class="modal-overlay" id="postModal" onclick="if(event.target==this) closePostModal()">
  <div class="card" style="width: 480px; padding: 30px; border: 1px solid var(--accent); position: relative;">
    <button type="button" onclick="closePostModal()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 20px; right: 20px;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
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
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Team Name</label>
        <input type="text" id="team-name" name="team_name" placeholder="Enter your team name..." style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 13px; margin-bottom: 16px; box-sizing: border-box;">
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Short Introduction</label>
        <textarea id="leader-looking-for" name="looking_for" rows="3" placeholder="Briefly introduce your Capstone Team..." style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 13px; resize: vertical; margin-bottom: 16px; box-sizing: border-box;"></textarea>
      </div>
      <div id="individual-fields" style="display:none;">
        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Short Introduction</label>
        <textarea id="individual-intro" rows="3" placeholder="Briefly introduce what you contribute to the team..." style="width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg2); color: var(--text); font-size: 13px; resize: vertical; margin-bottom: 16px; box-sizing: border-box;"></textarea>
      </div>
      <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text3); margin-bottom: 8px;">Expertise & Roles</label>
      <div class="tag-wrapper-box" style="position: relative; margin-bottom: 25px;">
        <div class="tag-input-field" id="tag-container" onclick="document.getElementById('tag-search-input').focus()">
          <input type="text" id="tag-search-input" placeholder="Type roles (e.g., Web Dev, UI Designer)..." oninput="filterTagSuggestions()" onfocus="filterTagSuggestions()">
        </div>
        <div class="tag-suggestions-box" id="tag-suggestions" style="display: none;"></div>
      </div>
      <div style="display: flex; gap: 10px;">
        <form method="POST" action="{{ route('recruitment.store') }}" style="flex: 1;" id="recruitment-form" onsubmit="publishPost(event)">
          @csrf
          <input type="hidden" name="recruitment_goal" id="recruitment-goal-input" value="build_a_team">
          <input type="hidden" name="looking_for" id="looking-for-input" value="">
          <input type="hidden" name="introduction" id="introduction-input" value="">
          <input type="hidden" name="team_name" id="team-name-hidden" value="">
          <input type="hidden" name="tags" id="tags-hidden-input" value="">
          <button type="submit" class="btn-primary" style="width: 100%;">Publish Post</button>
        </form>
        <button class="google-btn" style="flex: 1; border: none;" onclick="closePostModal()">Cancel</button>

      </div>
    </div>
  </div>
</div>

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

<form id="invite-request-form" method="POST" action="{{ route('invitations.store') }}" style="display:none;">
    @csrf
    <input type="hidden" name="receiver_id" id="ir-receiver-id">
    <input type="hidden" name="team_id" id="ir-team-id">
    <input type="hidden" name="type" id="ir-type">
</form>

{{-- Custom Confirm Modal --}}
<div id="customConfirmModal" style="display:none; position:fixed; inset:0; z-index:99999; align-items:center; justify-content:center; background:rgba(15,16,22,0.75); backdrop-filter:blur(6px); padding:20px;">
  <div id="ccm-card" style="background:#fff; border-radius:18px; padding:32px 28px 24px; width:100%; max-width:380px; box-shadow:0 24px 60px rgba(0,0,0,0.25); transform:scale(0.92); transition:transform 0.22s cubic-bezier(0.16,1,0.3,1); text-align:center; position:relative;">
    <div id="ccm-icon-wrap" style="width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; flex-shrink:0;"></div>
    <h3 id="ccm-title" style="font-size:17px; font-weight:700; color:#1e2128; margin:0 0 8px; letter-spacing:-0.3px;"></h3>
    <p id="ccm-message" style="font-size:13px; color:#6b7280; line-height:1.6; margin:0 0 24px;"></p>
    <div style="display:flex; gap:10px;">
      <button id="ccm-cancel" onclick="closeConfirm()" style="flex:1; padding:11px 16px; border-radius:10px; border:1px solid #e5e7eb; background:#f9fafb; color:#374151; font-size:13px; font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.15s;">Cancel</button>
      <button id="ccm-confirm" style="flex:1; padding:11px 16px; border-radius:10px; border:none; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; transition:all 0.15s; letter-spacing:0.1px;"></button>
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
const currentStudentTeamId = {{ auth()->user()->studentSchoolYear()->first()?->team_id ?? 'null' }};
const currentStudentId = {{ auth()->user()->student_id }};
const myRecruitmentGoal = '{{ $myRecruitmentGoal ?? '' }}';
let activeTags = [];
const leaderPresets = ["UI Designer", "App Developer", "Backend Dev", "Documentation", "Fullstack Dev", "Web Dev", "Mobile Dev", "Project Manager"];
const individualPresets = ["Leadership", "Research", "Design", "Testing", "Frontend Dev", "Database Admin", "QA Engineer", "Web Dev", "UI Designer", "Backend Dev"];

@if($myTeam)
function confirmLeaveTeam() {
    showConfirm({
        title: 'Leave Team',
        message: 'Are you sure you want to leave <strong>{{ addslashes($myTeam->team_name) }}</strong>? This action cannot be undone.',
        icon: 'leave',
        confirmText: 'Yes, Leave',
        confirmClass: 'danger',
        onConfirm: function() {
            document.getElementById('leave-team-form').submit();
        }
    });
}
@endif

// ── Custom Confirm Modal ──
const ccmIcons = {
    leave: { svg: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>', bg: 'rgba(220,38,38,0.08)', color: '#dc2626' },
    team:  { svg: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>', bg: 'rgba(22,163,74,0.08)', color: '#16a34a' },
    invite:{ svg: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>', bg: 'rgba(201,168,44,0.1)', color: '#b5941f' },
    request:{ svg: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>', bg: 'rgba(8,145,178,0.08)', color: '#0891b2' },
};
const ccmStyles = {
    danger:  { bg: '#dc2626', hover: '#b91c1c', text: '#fff' },
    success: { bg: '#16a34a', hover: '#15803d', text: '#fff' },
    primary: { bg: '#c9a82c', hover: '#b5941f', text: '#1e2128' },
};
let ccmCallback = null;

function showConfirm({ title, message, icon = 'request', confirmText = 'Confirm', confirmClass = 'primary', onConfirm }) {
    ccmCallback = onConfirm;
    const modal = document.getElementById('customConfirmModal');
    const card  = document.getElementById('ccm-card');
    const iconData = ccmIcons[icon] || ccmIcons.request;
    const styleData = ccmStyles[confirmClass] || ccmStyles.primary;

    document.getElementById('ccm-icon-wrap').style.background = iconData.bg;
    document.getElementById('ccm-icon-wrap').style.color = iconData.color;
    document.getElementById('ccm-icon-wrap').innerHTML = iconData.svg;
    document.getElementById('ccm-title').textContent = title;
    document.getElementById('ccm-message').innerHTML = message;

    const btn = document.getElementById('ccm-confirm');
    btn.textContent = confirmText;
    btn.style.background = styleData.bg;
    btn.style.color = styleData.text;
    btn.onclick = function() { const callback = ccmCallback; closeConfirm(); if (callback) callback(); };
    btn.onmouseenter = function() { this.style.background = styleData.hover; };
    btn.onmouseleave = function() { this.style.background = styleData.bg; };

    modal.style.display = 'flex';
    requestAnimationFrame(() => { card.style.transform = 'scale(1)'; });
}

function closeConfirm() {
    const modal = document.getElementById('customConfirmModal');
    const card  = document.getElementById('ccm-card');
    card.style.transform = 'scale(0.92)';
    setTimeout(() => { modal.style.display = 'none'; }, 180);
    ccmCallback = null;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConfirm();
});

function openPostModal() {
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

function sendInviteOrRequest(receiverId, teamId, type) {
    const isInvite = type === 'invite';
    showConfirm({
        title: isInvite ? 'Invite Student' : 'Send Join Request',
        message: isInvite
            ? 'Send a team invitation to this student?'
            : 'Send a request to join this team? Your post will remain visible until accepted.',
        icon: isInvite ? 'invite' : 'request',
        confirmText: isInvite ? 'Send Invite' : 'Send Request',
        confirmClass: 'primary',
        onConfirm: function() {
            document.getElementById('ir-receiver-id').value = receiverId;
            document.getElementById('ir-team-id').value = teamId;
            document.getElementById('ir-type').value = type;
            document.getElementById('invite-request-form').submit();
        }
    });
}

function viewPostDetails(cardEl, name, skills, lookingFor, teamName, projectTitle, studentNumber, isOwner, type, postOwnerId, postOwnerTeamId, avatarUrl) {
    const modal = document.getElementById('detailModal');

    const goalLabelEl = cardEl.querySelector('.goal-label');
    if (isOwner) {
        goalLabelEl.textContent = 'Your Post';
    } else {
        goalLabelEl.textContent = type === 'Leader' ? 'BUILD A TEAM' : 'Find a Team';
    }

    document.getElementById('detail-name').textContent = name;
    document.getElementById('detail-student-id').textContent = 'Student ID: ' + studentNumber;
    document.getElementById('detail-looking-for').textContent = lookingFor ? 'Looking For: ' + lookingFor : 'Looking For: -';
    document.getElementById('detail-team-name').textContent = 'Team: ' + (teamName || '-');
    
    const ownerMsg = document.getElementById('detail-owner-msg');
    const actionBtn = document.getElementById('action-btn-text');
    if (isOwner) {
        ownerMsg.style.display = 'block';
        actionBtn.textContent = 'Close';
        actionBtn.disabled = false;
        actionBtn.style.opacity = 1;
        actionBtn.style.cursor = 'pointer';
        actionBtn.onclick = function() { modal.classList.remove('active'); };
    } else {
        ownerMsg.style.display = 'none';
        actionBtn.disabled = false;
        actionBtn.style.opacity = 1;
        actionBtn.style.cursor = 'pointer';

        if (type === 'Leader') {
            // Viewer wants to send a join request to a build_a_team poster
            if (currentStudentTeamId) {
                // Already in a team — cannot request to join another
                actionBtn.textContent = 'Leave your team first';
                actionBtn.disabled = true;
                actionBtn.style.opacity = 0.5;
                actionBtn.style.cursor = 'not-allowed';
                actionBtn.onclick = null;
            } else if (myRecruitmentGoal === 'build_a_team') {
                // Current user posted build_a_team but has no team yet — still can't request to join another leader
                actionBtn.textContent = 'You are recruiting — cannot request';
                actionBtn.disabled = true;
                actionBtn.style.opacity = 0.5;
                actionBtn.style.cursor = 'not-allowed';
                actionBtn.onclick = null;
            } else {
                actionBtn.textContent = 'Send Request';
                actionBtn.onclick = function() {
                    sendInviteOrRequest(postOwnerId, postOwnerTeamId, 'request');
                };
            }
        } else {
            // find_a_team poster — current user can invite them if they have a team
            if (currentStudentTeamId) {
                actionBtn.textContent = 'Invite to Team';
                actionBtn.onclick = function() {
                    sendInviteOrRequest(postOwnerId, currentStudentTeamId, 'invite');
                };
            } else {
                actionBtn.textContent = 'Invite to Team (Requires Team)';
                actionBtn.disabled = true;
                actionBtn.style.opacity = 0.5;
                actionBtn.style.cursor = 'not-allowed';
                actionBtn.onclick = null;
            }
        }
    }

    const avatarEl = document.getElementById('detail-avatar');
    if (avatarUrl) {
        avatarEl.innerHTML = '<img src="' + avatarUrl + '" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">';
    } else {
        const initials = name.split(' ').map(function(n) { return n[0]; }).join('').substring(0, 2).toUpperCase();
        avatarEl.textContent = initials;
    }

    const skillContainer = document.getElementById('detail-skills');
    if (skills) {
        skillContainer.innerHTML = skills.split(',').map(function(s) {
            return '<span class="expertise-tag" style="margin:0; font-size:10px">' + s.trim() + '</span>';
        }).join('');
    } else {
        skillContainer.innerHTML = '';
    }

    modal.classList.add('active');
}

function updateModalUI() {
    const goal = document.getElementById('post-goal').value;
    const isLeader = (goal === 'leader');
    document.getElementById('modal-title').textContent = isLeader ? 'Build a Team' : 'Find a Team';
    document.getElementById('modal-note-text').innerHTML = isLeader 
        ? '<strong>CapsTip:</strong> A standard capstone team usually consists of 3-5 members. Setting this helps students know if you still have slots available.' 
        : '<strong>CapsTip:</strong> Highlight your best technical strengths. Teams search for specific skills when filling up their empty slots.';
    
    document.getElementById('leader-fields').style.display = isLeader ? 'block' : 'none';
    document.getElementById('individual-fields').style.display = isLeader ? 'none' : 'block';
    document.getElementById('recruitment-goal-input').value = isLeader ? 'build_a_team' : 'find_a_team';
    
    activeTags = [];
    renderActiveTags();
}

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

function filterTagSuggestions() {
    const goal = document.getElementById('post-goal').value;
    const query = document.getElementById('tag-search-input').value.toLowerCase().trim();
    const pool = (goal === 'leader') ? leaderPresets : individualPresets;
    const filtered = pool.filter(s => s.toLowerCase().includes(query) && !activeTags.includes(s));
    const box = document.getElementById('tag-suggestions');
    
    box.innerHTML = '';
    if (filtered.length === 0) {
        box.style.display = 'none';
        return;
    }
    
    filtered.forEach(tag => {
        const item = document.createElement('div');
        item.className = 'tag-suggestion-item';
        item.textContent = tag;
        item.onclick = function() { addTagToken(tag); };
        box.appendChild(item);
    });
    box.style.display = 'block';
}

function addTagToken(tag) {
    if (!activeTags.includes(tag)) {
        activeTags.push(tag);
    }
    document.getElementById('tag-search-input').value = '';
    document.getElementById('tag-suggestions').style.display = 'none';
    renderActiveTags();
}

function removeTagToken(tag) {
    activeTags = activeTags.filter(t => t !== tag);
    renderActiveTags();
}

function renderActiveTags() {
    const container = document.getElementById('tag-container');
    const input = document.getElementById('tag-search-input');
    container.querySelectorAll('.custom-tag-chip').forEach(el => el.remove());
    
    activeTags.forEach(tag => {
        const chip = document.createElement('div');
        chip.className = 'custom-tag-chip';
        chip.innerHTML = `${tag} <button type="button" class="custom-tag-chip-remove" onclick="removeTagToken('${tag.replace(/'/g, "\\'")}')">×</button>`;
        container.insertBefore(chip, input);
    });
    document.getElementById('tags-hidden-input').value = JSON.stringify(activeTags);
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

function publishPost(event) {
    if (activeTags.length === 0) {
        event.preventDefault();
        alert('Please select at least one expertise/role.');
        return;
    }
    const goal = document.getElementById('post-goal').value;
    if (goal === 'leader') {
        document.getElementById('team-name-hidden').value = document.getElementById('team-name').value;
        document.getElementById('looking-for-input').value = document.getElementById('leader-looking-for').value;
        document.getElementById('introduction-input').value = '';
    } else {
        document.getElementById('team-name-hidden').value = '';
        document.getElementById('looking-for-input').value = '';
        document.getElementById('introduction-input').value = document.getElementById('individual-intro').value;
    }
    document.getElementById('tags-hidden-input').value = JSON.stringify(activeTags);
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.tag-wrapper-box')) {
        document.getElementById('tag-suggestions').style.display = 'none';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    updateModalUI();
});
</script>
</body>
</html>
