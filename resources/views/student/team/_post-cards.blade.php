{{--
  Team selection cards. Rendered with the page and again for Load more and
  the filter tabs (TeamController::posts()), so both draw the same card.

  $posts: Recruitment[]; the viewer's context comes from
  TeamManagementPage::forCards(): $viewerTeamId, $viewerIsLeader,
  $viewerSection, $viewerCanInvite, $viewerCanRequest, $requestedTeamIds,
  $invitedStudentIds, $joiningAllowed.
--}}
@foreach ($posts as $recruit)
  @php
    $owner = $recruit->student;
    $ownerTeam = $owner?->team;
    $building = $recruit->recruitment_goal === 'build_a_team';
    $intro = $building ? ($recruit->looking_for ?? '') : ($recruit->introduction ?? $recruit->looking_for ?? '');
    $name = trim(($owner->f_name ?? '').' '.($owner->l_name ?? ''));
    $slots = $building ? $recruit->open_slots : null;
    $requested = $building && $ownerTeam && in_array($ownerTeam->team_id, $requestedTeamIds, true);
    $invited = ! $building && in_array($owner->student_id, $invitedStudentIds, true);
    $details = [
        $name, implode(', ', $recruit->tagLabels()), $recruit->looking_for ?? '', $ownerTeam->team_name ?? 'No Team',
        $ownerTeam->project_title ?? 'No project set', (string) $owner->student_number, false, $building ? 'Leader' : 'Individual',
        $owner->student_id, $ownerTeam->team_id ?? null, $owner->avatarUrl(), $recruit->introduction ?? '',
    ];
  @endphp
  <article class="ts-card{{ $building ? ' is-building' : ' is-seeking' }}" data-goal="{{ $recruit->recruitment_goal }}">
    <span class="goal-label" hidden>{{ $building ? 'BUILD A TEAM' : 'Find a Team' }}</span>
    <div class="ts-card-head">
      <span class="ts-avatar" aria-hidden="true">
        @if ($owner->avatarUrl())
          <img src="{{ $owner->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ $owner->avatarInitials() }}" onerror="this.replaceWith(this.dataset.initials)">
        @else
          {{ $owner->avatarInitials() }}
        @endif
      </span>
      <span class="ts-who">
        <span class="ts-name">{{ $name }}</span>
        <span class="ts-type"><i></i>{{ $building ? 'Building a team'.($slots !== null ? ' · '.$slots.' '.\Illuminate\Support\Str::plural('slot', $slots) : '') : 'Looking for a team' }}</span>
      </span>
    </div>
    @if ($intro)
      <p class="ts-intro" title="{{ $intro }}">{{ $intro }}</p>
    @endif
    @if ($building && $recruit->roles)
      <div class="ts-chips"><span class="ts-chips-k">Needs</span>@foreach ($recruit->roles as $role)<span class="ts-chip">{{ str_replace('/', ' / ', $role) }}</span>@endforeach</div>
    @elseif (! $building && $recruit->tagLabels() !== [])
      <div class="ts-chips"><span class="ts-chips-k">Roles</span>@foreach ($recruit->tagLabels() as $label)<span class="ts-chip">{{ str_replace('/', ' / ', $label) }}</span>@endforeach</div>
    @endif
    <div class="ts-actions">
      <button type="button" class="ts-btn" onclick="viewPostDetails(this.closest('.ts-card'), ...{{ Js::from($details) }})">View post</button>
      @if ($building && $requested)
        <span class="ts-state">Requested</span>
      @elseif (! $joiningAllowed && ($building ? ! $viewerTeamId : $viewerIsLeader))
        {{-- Joining is closed by the section's Team Rules: said on the card, not left out. --}}
        <span class="ts-state is-closed" title="Joining a team is closed in {{ $viewerSection }}"><svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Closed</span>
      @elseif ($building && $viewerCanRequest)
        <button type="button" class="ts-btn ts-btn-primary" onclick="viewPostDetails(this.closest('.ts-card'), ...{{ Js::from($details) }})">Ask to join</button>
      @elseif (! $building && $invited)
        <span class="ts-state is-invited">Invited</span>
      @elseif (! $building && $viewerCanInvite)
        <button type="button" class="ts-btn ts-btn-primary" onclick="viewPostDetails(this.closest('.ts-card'), ...{{ Js::from($details) }})">Invite to team</button>
      @endif
    </div>
  </article>
@endforeach
