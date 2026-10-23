{{--
  The team screen, for a member and for a leader: the hero with the team's
  stats, the members table and the defense panel. Data from
  TeamManagementPage (screens member, leader_open, leader_full).
--}}
@php
  $me = auth()->user();
  $seatsLeft = max(0, $memberCount - $teamSize);
  $rosterOpen = $capstoneStage === 1 && ! $teamLocked;
  $myLeaveRequest = $leaveRequests->firstWhere('student_id', $me->student_id);
  $asksCapsaToLeave = ! $isLeader || $leaderAsksCapsa;
  $roleLabel = fn (?string $role) => $role ? str_replace('/', ' / ', $role) : null;
@endphp

<section class="tm-hero" aria-label="Your team">
  <div class="tm-hero-top">
    <span class="tm-logo" aria-hidden="true">{{ mb_substr($myTeam->team_name, 0, 2) }}</span>
    <div class="tm-hero-text">
      <span class="tm-kicker">{{ $sectionLabel }} · Capstone SY {{ $mySchoolYear?->school_year }}</span>
      <h1 @class(['is-empty' => ! $myTeam->project_title])>{{ $myTeam->project_title ?: 'Project title not set yet' }}</h1>
      <div class="tm-chips">
        <span class="tm-team-name">{{ $myTeam->team_name }}</span>
        @if ($rosterOpen && $seatsLeft > 0)
          <span class="tm-chip is-forming"><i></i>Forming · {{ $seatsLeft }} {{ \Illuminate\Support\Str::plural('slot', $seatsLeft) }} open</span>
        @else
          <span class="tm-chip is-going"><i></i>On-going</span>
        @endif
        @if ($myTeam->is_confirmed)
          <span class="tm-chip">Verified</span>
        @elseif ($teamHasSubmittedPreProposals)
          <span class="tm-chip">Pending verification</span>
        @endif
      </div>
    </div>
    @if ($isLeader)
      <div class="tm-hero-actions">
        <button type="button" class="tm-act" onclick="openEditTeamModal()">Update team</button>
        <button type="button" class="tm-act" @if ($teamSize > 1) onclick="openTransferModal()" @else disabled title="Nobody to hand the team to yet" @endif>Transfer leadership</button>
        @unless ($leaderAsksCapsa)
          <form method="POST" action="{{ route('team.destroy') }}" id="leave-team-form">
            @csrf
            @method('DELETE')
            <button type="button" class="tm-act is-danger" onclick="confirmLeaveTeam()">Leave team</button>
          </form>
        @endunless
      </div>
    @endif
  </div>
  <dl class="tm-stats">
    <div>
      <dt>Members</dt>
      <dd>{{ $teamSize }} / {{ $memberCount }}
        <span class="tm-bars" aria-hidden="true">@for ($i = 0; $i < $memberCount; $i++)<span @class(['is-on' => $i < $teamSize])></span>@endfor</span>
      </dd>
    </div>
    <div>
      <dt>Your role</dt>
      <dd>{{ $isLeader ? 'Leader' : ($roleLabel($userRole) ?? 'Not picked yet') }}</dd>
    </div>
    @if ($screen === \App\Support\TeamManagementPage::LEADER_OPEN)
      <div>
        <dt>Invites sent</dt>
        <dd>{{ $outgoingInvitations->count() }} pending</dd>
      </div>
    @else
      <div>
        <dt>Current stage</dt>
        <dd>{{ $currentStage ?? 'Every defense passed' }}</dd>
      </div>
    @endif
    <div>
      <dt>Date formed</dt>
      <dd>{{ \Carbon\Carbon::parse($myTeam->created_at ?? now())->format('M j, Y') }}</dd>
    </div>
  </dl>
</section>

@if ($isLeader && ! $joiningAllowed && $rosterOpen && $seatsLeft > 0)
  <div class="tm-closed" role="status">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    <span><b>Joining a team is closed</b> in {{ $sectionLabel }}. You can still cancel pending invites.</span>
  </div>
@endif

<div class="tm-row">
  <section class="tm-card tm-members" aria-labelledby="tm-members-h">
    <div class="tm-card-head">
      <div class="tm-card-title">
        <h2 id="tm-members-h">Team members</h2>
        <span class="tm-count">{{ $teamSize }} / {{ $memberCount }}{{ $seatsLeft === 0 ? ' · Full' : '' }}</span>
      </div>
      @if ($asksCapsaToLeave)
        <button type="button" class="tm-leave" @if ($myLeaveRequest) disabled title="Your request is waiting on your CaPSA" @else onclick="openCapsaLeaveModal('leave')" aria-haspopup="dialog" @endif>
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
          {{ $myLeaveRequest ? 'Leave request with CaPSA' : 'Ask CaPSA to leave' }}
        </button>
      @endif
    </div>

    @if ($teamLocked)
      <div class="tm-note" role="status">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <span><b>Members locked{{ $teamLockedAt ? ' since '.$teamLockedAt->format('M j, Y') : '' }}.</b> Your team passed Title Defense, so no one can join, and a member leaves only with your CaPSA's approval.</span>
      </div>
    @endif

    <div class="tm-table" role="table" aria-label="Team members">
      <div class="tm-tr tm-th" role="row">
        <span role="columnheader">Member</span>
        <span role="columnheader">Role</span>
        <span role="columnheader"><span class="sr-only">Status</span></span>
      </div>

      @foreach ($teamMembers as $member)
        @php
          $memberName = trim(($member->f_name ?? '').' '.($member->l_name ?? '')) ?: ($member->email ?? 'Member');
          $isTeamLeader = (int) $member->student_id === (int) $myTeam->leader_id;
          $isMe = (int) $member->student_id === (int) $me->student_id;
        @endphp
        <div class="tm-tr" role="row">
          <span class="tm-who" role="cell">
            <span @class(['ts-avatar', 'is-gold' => $isTeamLeader || $isMe]) aria-hidden="true">
              @if ($member->avatarUrl())
                <img src="{{ $member->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ $member->avatarInitials() }}" onerror="this.replaceWith(this.dataset.initials)">
              @else
                {{ $member->avatarInitials() }}
              @endif
            </span>
            <b>{{ $memberName }}</b>
          </span>
          <span class="tm-role" role="cell">{{ $roleLabel($member->s_role_name) ?? 'Picking a role' }}</span>
          <span class="tm-tags" role="cell">
            @if ($isTeamLeader)<span class="tm-tag is-leader">LEADER</span>@endif
            @if ($isMe)<span class="tm-tag is-you">YOU</span>@endif
            @if ($leaveRequests->contains('student_id', $member->student_id))
              <span class="tm-tag is-capsa" title="A request about this member is waiting on your CaPSA">WITH CAPSA</span>
            @elseif ($teamLocked && $isLeader && ! $isMe)
              <button type="button" class="tm-link is-danger" onclick="openCapsaLeaveModal('remove', {{ $member->student_id }}, {{ Js::from($memberName) }})" title="Ask your CaPSA to remove this member">Remove</button>
            @elseif ($isLeader && ! $isMe && ! $myTeam->is_confirmed)
              <button type="button" class="tm-link is-danger" onclick="openRemoveMemberModal({{ $member->student_id }}, {{ Js::from($memberName) }}, {{ Js::from($member->email ?? '') }}, {{ Js::from(($member->course ?? '').($member->year_section ? ' · '.$member->year_section : '')) }}, {{ Js::from($member->avatarUrl()) }})">Remove</button>
            @endif
          </span>
        </div>
      @endforeach

      @foreach ($outgoingInvitations as $pendingInvite)
        @php $invitee = $pendingInvite->receiver; $inviteeName = trim(($invitee->f_name ?? '').' '.($invitee->l_name ?? '')); @endphp
        @if ($invitee)
          <div class="tm-tr is-pending" role="row">
            <span class="tm-who" role="cell">
              <span class="ts-avatar" aria-hidden="true">
                @if ($invitee->avatarUrl())
                  <img src="{{ $invitee->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ $invitee->avatarInitials() }}" onerror="this.replaceWith(this.dataset.initials)">
                @else
                  {{ $invitee->avatarInitials() }}
                @endif
              </span>
              <b>{{ $inviteeName }}</b>
            </span>
            <span class="tm-role" role="cell">Picks a role after joining</span>
            <span class="tm-tags" role="cell">
              <span class="tm-tag is-pending">PENDING</span>
              @if ($isLeader && ! $myTeam->is_confirmed)
                <button type="button" class="tm-link is-danger" onclick="cancelPendingInvite({{ $pendingInvite->id }}, {{ Js::from($inviteeName) }})">Cancel</button>
              @endif
            </span>
          </div>
        @endif
      @endforeach

      @if ($teamSeatsFree > 0 && ! $rosterOpen)
        {{-- The roster closes at Capstone 2, and after Title Defense: the
             seats left are said once, not drawn as slots nobody can fill. --}}
        <div class="tm-tr is-slot" role="row">
          <span class="tm-slot" role="cell">
            <span class="tm-slot-ring" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
            <b>{{ $teamLocked ? 'Members are locked.' : 'Members Addition no longer available.' }}</b>
            <span>{{ $teamLocked ? 'Your team passed Title Defense, so no one can be added.' : 'Capstone 2 has started, so a team keeps the members it finished Capstone 1 with.' }}</span>
          </span>
        </div>
      @elseif ($teamSeatsFree > 0)
        @for ($i = 0; $i < $teamSeatsFree; $i++)
          <div class="tm-tr is-slot" role="row">
            @if ($isLeader && $joiningAllowed)
              <button type="button" class="tm-slot is-open" role="cell" onclick="openOpenSlotInviteModal()" aria-haspopup="dialog">
                <span class="tm-slot-ring" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg></span>
                <b>Open slot</b><span>· Invite a classmate</span>
              </button>
            @elseif ($isLeader)
              <span class="tm-slot" role="cell" title="Joining a team is closed in {{ $sectionLabel }}">
                <span class="tm-slot-ring" aria-hidden="true"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                <b>Open slot</b><span>· Joining is closed</span>
              </span>
            @else
              <span class="tm-slot" role="cell">
                <span class="tm-slot-ring" aria-hidden="true"></span>
                <b>Open slot</b><span>· Waiting for a member</span>
              </span>
            @endif
          </div>
        @endfor
      @endif
    </div>
  </section>

  {{-- Defense Panel: who the CaPSA put on the team's next defense.
       Assigning a panel writes the same three experts onto every stage at
       once, so this is "the" panel rather than one for a single date. --}}
  <section class="tm-card tm-panel" aria-labelledby="tm-panel-h">
    <div class="tm-card-head">
      <h2 id="tm-panel-h">Defense panel</h2>
      @if ($panelDefenseType)<span class="tm-count is-blue">{{ $panelDefenseType }}</span>@endif
    </div>
    @if ($panel->isNotEmpty())
      <ul class="tm-panel-list">
        @foreach ($panel as $seat)
          @php $ex = $seat->expert; @endphp
          <li>
            <span @class(['ts-avatar', 'is-ink' => $seat->panel_role === 'Panel Chair']) aria-hidden="true">
              @if ($ex?->hasAvatarImage())
                <img src="{{ $ex->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ $ex?->avatarInitials() }}" onerror="this.replaceWith(this.dataset.initials)">
              @else
                {{ $ex?->avatarInitials() }}
              @endif
            </span>
            <span class="tm-panel-who"><b>{{ $ex?->name ?? 'Unknown' }}</b><span>{{ $seat->panel_role }}</span></span>
          </li>
        @endforeach
      </ul>
    @else
      <p class="tm-panel-empty">Not assigned yet. Your CaPSA sets your defense panel once your team is ready.</p>
    @endif
  </section>
</div>

@if ($leaveRequests->isNotEmpty())
  <section class="tm-card" aria-labelledby="tm-waiting-h">
    <div class="tm-card-head">
      <div class="tm-card-title">
        <h2 id="tm-waiting-h">Waiting on your CaPSA</h2>
        <span class="tm-count">{{ $leaveRequests->count() }}</span>
      </div>
    </div>
    <div class="tm-requests">
      @foreach ($leaveRequests as $rr)
        @php
          $rrMember = trim(($rr->student->f_name ?? '').' '.($rr->student->l_name ?? '')) ?: 'A member';
          $rrBy = trim(($rr->requester->f_name ?? '').' '.($rr->requester->l_name ?? '')) ?: 'The leader';
          $rrMine = (int) $rr->requested_by === (int) $me->student_id;
          $rrDeadline = $rr->autoDeclinesAt();
          $rrHoursLeft = max(0, (int) ceil(now()->diffInMinutes($rrDeadline, false) / 60));
        @endphp
        <div class="tm-request">
          <div class="tm-request-body">
            <b>{{ $rr->type === 'leave' ? $rrMember.' asked to leave' : $rrBy.' asked to remove '.$rrMember }}</b>
            <p>“{{ $rr->reason }}”</p>
            <div class="tm-request-meta">
              <span>Sent {{ $rr->created_at->diffForHumans() }}</span>
              <span @class(['tm-clock', 'is-soon' => $rrHoursLeft <= 24]) title="If your CaPSA has not decided by {{ $rrDeadline->format('M j, g:i A') }}, it is declined automatically">
                <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                Auto-declines {{ $rrHoursLeft <= 24 ? 'in '.max(1, $rrHoursLeft).' '.(max(1, $rrHoursLeft) === 1 ? 'hour' : 'hours') : $rrDeadline->format('M j') }} without a decision
              </span>
            </div>
          </div>
          @if ($rrMine)
            <form method="POST" action="{{ route('team.capsa-leave-requests.update', $rr->id) }}">
              @csrf
              @method('PATCH')
              <input type="hidden" name="status" value="withdrawn">
              <button type="submit" class="tm-act">Withdraw</button>
            </form>
          @endif
        </div>
      @endforeach
    </div>
  </section>
@endif

{{-- Declined by the clock, not the CaPSA: said plainly, with the way to ask again. --}}
@if ($autoDeclinedRequests->isNotEmpty())
  <section class="tm-card" aria-labelledby="tm-declined-h">
    <div class="tm-card-head">
      <h2 id="tm-declined-h">Declined automatically</h2>
    </div>
    <p class="tm-card-sub">Your CaPSA did not act on these within {{ \App\Models\MemberLeaveRequest::DECIDE_WITHIN_DAYS }} days. You can send the request again.</p>
    <div class="tm-requests">
      @foreach ($autoDeclinedRequests as $rr)
        @php
          $rrMember = trim(($rr->student->f_name ?? '').' '.($rr->student->l_name ?? '')) ?: 'A member';
          $rrBy = trim(($rr->requester->f_name ?? '').' '.($rr->requester->l_name ?? '')) ?: 'The leader';
        @endphp
        <div class="tm-request">
          <div class="tm-request-body">
            <b>{{ $rr->type === 'leave' ? $rrMember.' asked to leave' : $rrBy.' asked to remove '.$rrMember }}</b>
            <span class="tm-clock">
              <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
              Auto-declined · No action from CaPSA in {{ \App\Models\MemberLeaveRequest::DECIDE_WITHIN_DAYS }} days
            </span>
            <p>“{{ $rr->reason }}”</p>
            <div class="tm-request-meta"><span>Sent {{ $rr->created_at->format('M j') }} · declined {{ $rr->decided_at?->format('M j, g:i A') }}</span></div>
          </div>
        </div>
      @endforeach
    </div>
  </section>
@endif
