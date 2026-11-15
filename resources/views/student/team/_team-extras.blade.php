{{--
  A team's own cards under its members: the leader's recruitment post and
  the join requests waiting on them. Data from TeamManagementPage.
--}}
@php
  $showPost = $myRecruitment && ! (isset($postHidden) && $postHidden);
@endphp
@if ($showPost || $incomingRequests->isNotEmpty())
<div class="tm-row">
  @if ($showPost)
    @php
      $postBuilds = $myRecruitment->recruitment_goal === 'build_a_team';
      $postText = $postBuilds ? ($myRecruitment->looking_for ?? '') : ($myRecruitment->introduction ?? $myRecruitment->looking_for ?? '');
    @endphp
    <section class="tm-card tm-post" id="my-post-section" aria-labelledby="tm-post-h">
      <div class="tm-card-head">
        <div class="tm-card-title">
          <h2 id="tm-post-h">Your recruitment post</h2>
          <span @class(['tm-chip', 'is-forming' => $postBuilds, 'is-seeking' => ! $postBuilds])><i></i>{{ $postBuilds ? 'Building a team' : 'Looking for a team' }}</span>
          @if ($isTeamFull && $postBuilds)<span class="tm-chip">Team full</span>@endif
        </div>
        <div class="tm-post-actions">
          <button type="button" class="tm-act" onclick="openEditModal()">Edit post</button>
          <button type="button" class="tm-act is-danger" onclick="openDeleteModal()">Delete</button>
        </div>
      </div>
      @if ($postText)<p class="tm-post-text">{{ $postText }}</p>@endif
      @if ($myRecruitment->tagLabels() !== [])
        <div class="ts-chips"><span class="ts-chips-k">Needs</span>@foreach ($myRecruitment->tagLabels() as $label)<span class="ts-chip">{{ $label }}</span>@endforeach</div>
      @endif
      @if ($myRecruitment->secondaryLabels() !== [])
        <div class="ts-chips"><span class="ts-chips-k">Secondary</span>@foreach ($myRecruitment->secondaryLabels() as $skill)<span class="ts-chip">{{ $skill }}</span>@endforeach</div>
      @endif
    </section>
  @endif

  @if ($incomingRequests->isNotEmpty())
    <section class="tm-card tm-requests-card" aria-labelledby="tm-req-h">
      <div class="tm-card-head">
        <div class="tm-card-title">
          <h2 id="tm-req-h">Join requests</h2>
          <span class="tm-count">{{ $incomingRequests->count() }} new</span>
        </div>
      </div>
      <div class="nt-invite-list">
        @foreach ($incomingRequests as $request)
          @php $requesterName = trim(($request->sender->f_name ?? 'Someone').' '.($request->sender->l_name ?? '')); @endphp
          <div class="nt-invite">
            <div class="nt-invite-head is-static">
              <span class="ts-avatar" aria-hidden="true">
                @if ($request->sender->avatarUrl())
                  <img src="{{ $request->sender->avatarUrl() }}" alt="" referrerpolicy="no-referrer" data-initials="{{ $request->sender->avatarInitials() }}" onerror="this.replaceWith(this.dataset.initials)">
                @else
                  {{ $request->sender->avatarInitials() }}
                @endif
              </span>
              <span class="nt-invite-who">
                <span><b>{{ $requesterName }}</b> wants to join</span>
                <span class="nt-invite-sub">{{ $request->sender->course ?? '' }}{{ $request->sender->year_section ? ' · '.$request->sender->year_section : '' }}</span>
              </span>
            </div>
            <div class="nt-invite-actions">
              <form method="POST" action="{{ route('invitations.update', $request->id) }}" id="accept-form-{{ $request->id }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="accepted">
                <button type="button" class="nt-btn-dark"
                  @if ($joiningAllowed) onclick="showConfirm({title:'Accept Request',message:'Let <strong>' + {{ Js::from(e($requesterName)) }} + '</strong> join your team? Their post will be removed.',icon:'team',confirmText:'Accept',confirmClass:'success',onConfirm:function(){document.getElementById('accept-form-{{ $request->id }}').submit();}})" @else disabled title="Joining a team is closed in {{ $sectionLabel }}" @endif>Accept</button>
              </form>
              <form method="POST" action="{{ route('invitations.update', $request->id) }}" id="decline-form-{{ $request->id }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="declined">
                <button type="button" class="nt-btn-line" onclick="openDeclineModal({{ $request->id }}, {{ Js::from(trim(($request->sender->f_name ?? '').' '.($request->sender->l_name ?? ''))) }}, '{{ $request->type }}', {{ Js::from($request->team->team_name ?? '') }}, {{ Js::from($request->sender->avatarUrl()) }}, {{ Js::from($request->sender->email ?? '') }}, {{ Js::from(($request->sender->course ?? '').($request->sender->year_section ? ' · '.$request->sender->year_section : '')) }})">Decline</button>
              </form>
            </div>
          </div>
        @endforeach
      </div>
    </section>
  @endif
</div>
@endif
