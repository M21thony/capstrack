{{--
  A team's own cards above Team selection: the leader's recruitment post and
  the join requests waiting on them. Data from TeamManagementPage.
--}}
@if (($myRecruitment && ! (isset($postHidden) && $postHidden)) || $incomingRequests->isNotEmpty())
<div class="team-container">
  <div style="display: flex; flex-direction: column; gap: 20px;">
    @if($myRecruitment && ($myRecruitment->recruitment_goal !== 'build_a_team' || $myTeam))
      @php
        $recruitIsLeader = $myRecruitment->recruitment_goal === 'build_a_team';
        $goalLabel = $recruitIsLeader ? 'BUILD A TEAM' : 'Find a Team';
        $ringClass = $recruitIsLeader ? 'hiring' : 'looking';
        $dotColor = $recruitIsLeader ? '#22c55e' : '#3b82f6';
        $initials = auth()->user()->avatarInitials();
        $introText = $recruitIsLeader
          ? ($myRecruitment->looking_for ?? '')
          : ($myRecruitment->introduction ?? $myRecruitment->looking_for ?? '');
      @endphp
      @if(!(isset($postHidden) && $postHidden))
      <div class="my-post-wrapper" id="my-post-section">
        <div class="my-post-header-row">
          <div class="my-post-header-title">
            <div class="my-post-icon">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
              <div class="my-post-title">Your Post</div>
              <div class="my-post-subtitle">Manage your recruitment post</div>
            </div>
          </div>
          <div class="my-post-header-actions">
            <button type="button" class="btn btn-edit-post" onclick="openEditModal()">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Edit Post
            </button>
            <button type="button" class="btn" style="padding:8px 16px; font-size:12px; background:var(--red-bg); color:var(--red); border:1px solid rgba(220,38,38,0.2);" onclick="openDeleteModal()">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              Delete
            </button>
          </div>
        </div>
        <div class="my-post-card" id="my-active-post">
          <div class="avatar-ring {{ $ringClass }}">
            @if (auth()->user()->avatarUrl())
              <img src="{{ auth()->user()->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ $initials }}" onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'avatar-text',textContent:this.dataset.initials}))">
            @else
              <div class="avatar-text">{{ $initials }}</div>
            @endif
            <div class="status-badge-dot" style="background:{{ $dotColor }}"></div>
          </div>
          <div class="my-post-body">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
              <div class="my-post-name">{{ auth()->user()->f_name }} {{ auth()->user()->l_name }}</div>
              <div class="my-post-goal {{ $recruitIsLeader ? 'is-leader' : 'is-member' }}">{{ $goalLabel }}</div>
              @if($isTeamFull && $recruitIsLeader)
                <span class="my-post-closed">TEAM FULL</span>
              @endif
            </div>
            @if($recruitIsLeader && $myTeam)
              <div style="display:flex; align-items:center; gap:6px; font-size:11px; color:var(--text3);">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>{{ $myTeam->team_name }}</span>
              </div>
            @endif
            @if($introText)
              <div class="my-post-text">{{ $introText }}</div>
            @endif
            @if($myRecruitment->tagLabels() !== [])
              <div class="my-post-tags">
                @foreach($myRecruitment->tagLabels() as $label)
                  <span class="expertise-tag">{{ $label }}</span>
                @endforeach
              </div>
            @endif
            @if($myRecruitment->skills)
              <div class="feed-post-skills"><b>Skills</b>{{ implode(', ', $myRecruitment->skills) }}</div>
            @endif
          </div>
        </div>
      </div>
      @endif
    @endif
  </div>

  @if($incomingRequests->isNotEmpty())
  <div style="display: flex; flex-direction: column; gap: 14px;">
    <div class="card">
      <div class="section-title" style="display: flex; justify-content: space-between; align-items: center;">
        Join Requests
        <span style="background: var(--bg3); color: var(--text); border: 1px solid var(--border); padding: 2px 6px; border-radius: 4px; font-size: 10px;">{{ $incomingRequests->count() }} New</span>
      </div>
      <div style="margin-top: 15px;">
        @foreach($incomingRequests as $request)
          <div class="invitation-card">
            <div style="display: flex; gap: 10px; align-items: center;">
              <div class="avatar" style="width: 32px; height: 32px; font-size: 11px; overflow: hidden; flex-shrink: 0;">
                @if ($request->sender->avatarUrl())
                  <img src="{{ $request->sender->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ strtoupper(substr($request->sender->f_name ?? 'U', 0, 1) . substr($request->sender->l_name ?? '', 0, 1)) }}" onerror="this.replaceWith(this.dataset.initials)" style="width: 100%; height: 100%; object-fit: cover;">
                @else
                  {{ strtoupper(substr($request->sender->f_name ?? 'U', 0, 1) . substr($request->sender->l_name ?? '', 0, 1)) }}
                @endif
              </div>
              <div>
                <div style="font-size: 12px; font-weight: 600;">{{ $request->sender->f_name ?? 'Someone' }} {{ $request->sender->l_name ?? '' }} wants to join</div>
                <div style="font-size: 10px; color: var(--text3);">Requested to join your team</div>
              </div>
            </div>
            <div class="invitation-actions">
              <form method="POST" action="{{ route('invitations.update', $request->id) }}" id="accept-form-{{ $request->id }}" style="display:inline;">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="accepted">
                <button class="btn-accept" type="button" style="cursor:pointer; border:none; outline:none;"
                  onclick="if(!JOINING_ALLOWED){showToast(JOINING_CLOSED_MSG,'error');return;}showConfirm({title:'Accept Request',message:'Let <strong>' + {{ Js::from(e(($request->sender->f_name ?? '') . ' ' . ($request->sender->l_name ?? ''))) }} + '</strong> join your team? Their post will be removed.',icon:'team',confirmText:'Accept',confirmClass:'success',onConfirm:function(){document.getElementById('accept-form-{{ $request->id }}').submit();}})">
                  Accept Request
                </button>
              </form>
              <form method="POST" action="{{ route('invitations.update', $request->id) }}" id="decline-form-{{ $request->id }}" style="display:inline;">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="declined">
                <button class="btn-decline" title="Decline" type="button" onclick="openDeclineModal({{ $request->id }}, {{ Js::from($request->sender->f_name . ' ' . $request->sender->l_name) }}, '{{ $request->type }}', {{ Js::from($request->team->team_name ?? '') }}, {{ Js::from($request->sender->avatarUrl()) }}, {{ Js::from($request->sender->email ?? '') }}, {{ Js::from(($request->sender->course ?? '') . ($request->sender->year_section ? ' · ' . $request->sender->year_section : '')) }})">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
              </form>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
  @endif
</div>
@endif
