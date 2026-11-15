{{--
  The no-team screen: what to do first, and the invites waiting.
  Data from TeamManagementPage (screen no_team).
--}}
<div class="nt-row">
  <section class="nt-hero" aria-labelledby="nt-h">
    <div class="nt-hero-text">
      <span class="nt-kicker">{{ $sectionLabel }} · Capstone SY {{ $mySchoolYear?->school_year ?? '' }}</span>
      <h1 id="nt-h">You’re not in a team yet</h1>
      @if ($capstoneStage !== 1)
        <p>Capstone 2 has started, so teams keep the members they finished Capstone 1 with. New teams and recruitment posts are closed. Speak to your CaPSA/TSA if you are not on a team.</p>
      @elseif ($myRecruitment)
        <p>Your post is up: {{ $myRecruitment->recruitment_goal === 'build_a_team' ? 'you are building a team' : 'you are looking for a team' }}. Classmates in your section can see it{{ $myRecruitment->recruitment_goal === 'find_a_team' ? ' and invite you' : '' }}.</p>
      @else
        <p>Start a team and look for members, or post that you’re available so a team can invite you. Teams have up to {{ $memberCount }} members.</p>
      @endif
    </div>
    @if ($capstoneStage === 1 && (! $creationAllowed || ! $joiningAllowed))
      <div class="nt-closed" role="status">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <span>
          @if (! $creationAllowed && ! $joiningAllowed)
            <b>Creating and joining teams are closed</b> in {{ $sectionLabel }}. Your CaPSA/TSA has turned them off for now. Speak to your CaPSA/TSA if you are not on a team.
          @elseif (! $creationAllowed)
            <b>Creating a team is closed</b> in {{ $sectionLabel }}. You can still post that you’re looking for a team and accept invites.
          @else
            <b>Joining a team is closed</b> in {{ $sectionLabel }}. You can still build your own team. Invites can be declined but not accepted.
          @endif
        </span>
      </div>
    @endif
    @if ($capstoneStage === 1 && $myRecruitment)
      @php
        $postBuilds = $myRecruitment->recruitment_goal === 'build_a_team';
        $postText = $postBuilds ? ($myRecruitment->looking_for ?? '') : ($myRecruitment->introduction ?? $myRecruitment->looking_for ?? '');
      @endphp
      <div class="nt-mypost" id="my-post-section">
        <span class="nt-mypost-goal">{{ $postBuilds ? 'Building a team' : 'Looking for a team' }}</span>
        @if ($postText)<p>{{ $postText }}</p>@endif
        @if ($myRecruitment->tagLabels() !== [] || $myRecruitment->secondaryLabels() !== [])
          <div class="nt-mypost-tags">@foreach ($myRecruitment->tagLabels() as $label)<span>{{ $label }}</span>@endforeach @foreach ($myRecruitment->secondaryLabels() as $skill)<span>{{ $skill }}</span>@endforeach</div>
        @endif
      </div>
    @endif
    @if ($capstoneStage === 1)
      <div class="nt-actions">
        @if ($myRecruitment)
          <button type="button" class="nt-btn nt-btn-primary" onclick="openEditModal()">Edit your post</button>
          <button type="button" class="nt-btn" onclick="openDeleteModal()">Delete post</button>
        @else
          <button type="button" class="nt-btn nt-btn-primary" data-nt-build
            @if ($creationAllowed) onclick="openPostModal()" @else disabled title="Creating a team is closed in {{ $sectionLabel }}" @endif>
            @if ($creationAllowed)
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            @else
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            @endif
            Build a team
          </button>
          <button type="button" class="nt-btn" data-nt-seek
            @if ($joiningAllowed) onclick="openPostModal('individual')" @else disabled title="Joining a team is closed in {{ $sectionLabel }}" @endif>
            @if ($joiningAllowed)
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            @else
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            @endif
            Post: looking for a team
          </button>
        @endif
      </div>
    @endif
    <ol class="nt-steps">
      <li><span>1</span>Post or accept an invite</li>
      <li><span>2</span>Fill your team</li>
      <li><span>3</span>Get verified</li>
    </ol>
  </section>

  <section class="nt-invites" aria-labelledby="nt-inv-h">
    <div class="nt-invites-head">
      <h2 id="nt-inv-h">Team invites</h2>
      @if ($invites->isNotEmpty())<span class="nt-count">{{ $invites->count() }} pending</span>@endif
    </div>
    <div class="nt-invite-list">
      @foreach ($invites as $invite)
        @php $detail = $inviteDetails[$invite->id]; @endphp
        <div class="nt-invite">
          <button type="button" class="nt-invite-head" onclick="document.getElementById('invite-detail-{{ $invite->id }}').classList.add('active')" aria-haspopup="dialog">
            <span class="ts-avatar" aria-hidden="true">
              @if ($invite->sender?->avatarUrl())
                <img src="{{ $invite->sender->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ $invite->sender->avatarInitials() }}" onerror="this.replaceWith(this.dataset.initials)">
              @else
                {{ $invite->sender?->avatarInitials() }}
              @endif
            </span>
            <span class="nt-invite-who">
              <span><b>{{ $detail['leader'] }}</b> invited you</span>
              <span class="nt-invite-sub">{{ $invite->team->team_name ?? 'A team' }} · {{ $detail['members'] }} / {{ $detail['limit'] }}</span>
            </span>
            <span class="nt-invite-view">View<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></span>
          </button>
          <div class="nt-invite-actions">
            <form method="POST" action="{{ route('invitations.update', $invite->id) }}" id="accept-form-{{ $invite->id }}">
              @csrf
              @method('PATCH')
              <input type="hidden" name="status" value="accepted">
              <button type="button" class="nt-btn-dark" @if ($joiningAllowed) onclick="acceptInvite({{ $invite->id }}, {{ Js::from($invite->team->team_name ?? 'this team') }})" @else disabled title="Joining a team is closed in {{ $sectionLabel }}" @endif>Accept</button>
            </form>
            <form method="POST" action="{{ route('invitations.update', $invite->id) }}" id="decline-form-{{ $invite->id }}">
              @csrf
              @method('PATCH')
              <input type="hidden" name="status" value="declined">
              <button type="button" class="nt-btn-line" onclick="openDeclineModal({{ $invite->id }}, {{ Js::from(trim(($invite->sender->f_name ?? '').' '.($invite->sender->l_name ?? ''))) }}, '{{ $invite->type }}', {{ Js::from($invite->team->team_name ?? '') }}, {{ Js::from($invite->sender?->avatarUrl()) }}, {{ Js::from($invite->sender->email ?? '') }}, {{ Js::from(($invite->sender->course ?? '').($invite->sender?->year_section ? ' · '.$invite->sender->year_section : '')) }})">Decline</button>
            </form>
          </div>
        </div>
      @endforeach

      @foreach ($outgoingRequests as $request)
        <div class="nt-invite is-mine">
          <div class="nt-invite-head is-static">
            <span class="ts-avatar is-amber" aria-hidden="true"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
            <span class="nt-invite-who">
              <span>You asked to join <b>{{ $request->team->team_name ?? 'a team' }}</b></span>
              <span class="nt-invite-sub">Waiting for the leader · one request at a time</span>
            </span>
            <span class="nt-pending">PENDING</span>
          </div>
        </div>
      @endforeach

      @if ($invites->isEmpty() && $outgoingRequests->isEmpty())
        <div class="nt-empty">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
          No pending invites
        </div>
      @endif
    </div>
  </section>
</div>

@foreach ($invites as $invite)
  @php $detail = $inviteDetails[$invite->id]; @endphp
  <x-team-modal id="invite-detail-{{ $invite->id }}" title-id="invite-detail-{{ $invite->id }}-t">
    <div class="tmd-person">
      <span class="ts-avatar is-lg" aria-hidden="true">{{ $invite->sender?->avatarInitials() }}</span>
      <div>
        <h2 id="invite-detail-{{ $invite->id }}-t">{{ $invite->team->team_name ?? 'A team' }}</h2>
        <span class="tmd-sub">{{ $detail['leader'] }} invited you to their team</span>
      </div>
    </div>
    @if ($detail['title'])<p class="tmd-desc">{{ $detail['title'] }}</p>@endif
    <dl class="tmd-rows">
      <div><dt>Members</dt><dd>{{ $detail['members'] }} / {{ $detail['limit'] }}</dd></div>
      <div><dt>Leader</dt><dd>{{ $detail['leader'] }}</dd></div>
    </dl>
    @if ($detail['roles'] !== [])
      <div class="tmd-chips"><span>Looking for</span>@foreach ($detail['roles'] as $role)<b>{{ str_replace('/', ' / ', $role) }}</b>@endforeach</div>
    @endif
    <div class="tmd-actions">
      <button type="button" class="nt-btn nt-btn-primary" @if ($joiningAllowed) onclick="document.getElementById('invite-detail-{{ $invite->id }}').classList.remove('active'); acceptInvite({{ $invite->id }}, {{ Js::from($invite->team->team_name ?? 'this team') }})" @else disabled title="Joining a team is closed in {{ $sectionLabel }}" @endif>Accept invite</button>
      <button type="button" class="nt-btn-line" onclick="document.getElementById('invite-detail-{{ $invite->id }}').classList.remove('active'); document.querySelector('#decline-form-{{ $invite->id }} button').click()">Decline</button>
    </div>
  </x-team-modal>
@endforeach

<script nonce="{{ Vite::cspNonce() }}">
function acceptInvite(id, teamName) {
    if (!JOINING_ALLOWED) { showToast(JOINING_CLOSED_MSG, 'error'); return; }
    var safe = String(teamName).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; });
    showConfirm({ title: 'Join Team', message: 'Accept the invitation from <strong>' + safe + '</strong>? Your current post will be removed.', icon: 'team', confirmText: 'Accept', confirmClass: 'success', onConfirm: function () { document.getElementById('accept-form-' + id).submit(); } });
}
</script>
