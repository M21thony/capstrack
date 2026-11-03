<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content" />
<title>Experts - CapsTrack</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link href="{{ \App\Support\PublicAsset::url('css/fonts.css') }}" rel="stylesheet">
@vite(['resources/student-ui/styles.css', 'resources/student-ui/Styles/experts.css'])
  @include('partials.render-whole')
</head>
<body>
@include('student.partials.sidebar')

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle navigation">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Experts</div>
    <div class="topbar-actions">
      @include('student.partials.notification-dropdown')
    </div>
  </div>

  <div class="content">
  @php
    $roleNames = ['adviser', 'consultant', 'grammarian'];
    $roleBlurbs = [
      'adviser' => 'Academic guidance and concerns',
      'consultant' => 'Capstone project review',
      'grammarian' => 'Grammar and language review',
    ];

    // An assignment whose expert account is gone is nobody: it showed as an
    // "Unknown" card and made the count read "4 of 3".
    $approvedAssignments = $assignments->where('status', 'approved')->filter(fn ($a) => $a->expert);

    // Keyed by the role the team actually engaged them as. Anyone approved
    // before roles were recorded has no slot, so they are listed after the
    // three rather than dropped from the page.
    $filledSlots = [];
    $unslotted = [];
    foreach ($approvedAssignments as $a) {
      $r = $a->engaged_role_name;
      if ($r && in_array($r, $roleNames, true) && ! isset($filledSlots[$r])) { $filledSlots[$r] = $a; }
      else { $unslotted[] = $a; }
    }
    $filledCount = count($filledSlots) + count($unslotted);

    // An approved expert is yours, not someone to browse or apply to again.
    $settledExpertIds = $approvedAssignments->pluck('expert_id')->filter()->all();
    $shownExperts = $experts->whereNotIn('expert_id', $settledExpertIds)->values();

    // Open experts on one side, full and not-taking on the other. The first
    // four open ones whose skills cover the team's tags are marked as a fit.
    $openCount = $shownExperts->where('is_open', true)->count();
    $fullCount = $shownExperts->filter(fn ($e) => ! $e->is_open && $e->hasSetPrograms() && $e->max_teams > 0)->count();
    $notTakingCount = $shownExperts->count() - $openCount - $fullCount;
    $fittingIds = $shownExperts->filter(fn ($e) => $e->is_open && $e->matched_tags)->pluck('expert_id');
    $fitIds = $fittingIds->take(4)->all();

    // Waiting on somebody, then answered. Approved ones are on the strip.
    $waitingRequests = $assignments->where('status', 'pending')->filter(fn ($a) => $a->expert)->values();
    $closedRequests = $assignments->whereNotIn('status', ['approved', 'pending'])->filter(fn ($a) => $a->expert)->values();
    $openRequests = $waitingRequests->concat($closedRequests);

    $removalByAssignment = collect($pendingRemovalIds);
    $activeFilled = $filledSlots[$activeRole] ?? null;
    $activePending = (! $activeFilled && isset($rolesSpokenFor[$activeRole]) && $rolesSpokenFor[$activeRole]->status === 'pending')
      ? $rolesSpokenFor[$activeRole] : null;

    $canApply = $team && $isLeader && $teamConfirmed && $approvedProposal;
    $studentProgram = \App\Models\Student::mapCourseToProgram(auth()->user()->course ?? '');

    // Who a card is, what they do, how much room they have and what this
    // team may do about them - read by the profile and apply dialogs.
    $expertPayloads = [];
    // The team's own experts too, so Profile works on the Your experts cards.
    $ownRoleOf = $approvedAssignments->mapWithKeys(fn ($a) => [$a->expert_id => $a->engaged_role_name]);
    foreach ($shownExperts->concat($approvedAssignments->pluck('expert')) as $expert) {
      $ownRole = $ownRoleOf->get($expert->expert_id);
      if ($ownRoleOf->has($expert->expert_id) && ! isset($expert->slots_used)) {
        $expert->slots_used = $expert->teamAssignments()->where('status', 'approved')->count();
      }
      $max = (int) ($expert->max_teams ?? 0);
      $used = (int) $expert->slots_used;
      $isFull = $max > 0 && $used >= $max;
      $mine = $assignments->where('expert_id', $expert->expert_id);
      $hasPending = $mine->where('status', 'pending')->isNotEmpty();
      $roles = $expert->roles->pluck('role_name')->filter(fn ($r) => in_array($r, $roleNames, true))->values();

      // Each role the leader can apply for, carrying the pivot key the
      // application records. A role the team has already asked somebody for
      // is left off.
      $choices = $expert->roles
        ->filter(fn ($r) => in_array($r->role_name, $roleNames, true))
        ->reject(fn ($r) => isset($rolesSpokenFor[$r->role_name]))
        ->map(fn ($r) => ['expert_role_id' => $r->pivot->expert_role_id, 'name' => $r->role_name, 'label' => ucfirst($r->role_name)])
        ->values();

      $status = null;
      if ($hasPending) {
        $status = ['text' => 'Application pending', 'tone' => 'amber'];
      } elseif (! $expert->hasSetPrograms()) {
        $status = ['text' => 'Not accepting teams yet', 'tone' => 'gray'];
      } elseif ($isFull) {
        $status = ['text' => 'No slots left', 'tone' => 'red'];
      } elseif ($choices->isEmpty() && $activePending) {
        $status = ['text' => 'Waiting on your other application', 'tone' => 'gray'];
      }

      if ($ownRoleOf->has($expert->expert_id)) { $status = null; }
      $tagRole = $ownRole && in_array($ownRole, $roleNames, true) ? $ownRole : $activeRole;

      $expertPayloads[$expert->expert_id] = [
        'id' => $expert->expert_id,
        'name' => $expert->name,
        // Written the way the expert's own profile page previews it.
        'profileName' => trim($expert->first_name.' '.$expert->last_name).($expert->degreeText() ? ', '.$expert->degreeText() : ''),
        'email' => $expert->email,
        'initials' => $expert->avatarInitials(),
        'avatar' => $expert->hasAvatarImage() ? $expert->avatarUrl() : null,
        'roles' => $roles->all(),
        // What they offer in the role being browsed - kept apart from their other sides.
        'tags' => $expert->expertiseFor($tagRole)->all(),
        'side' => \App\Models\ExpertExpertise::SIDES[$tagRole] ?? ucfirst($tagRole),
        'used' => $used,
        'max' => $max,
        'choices' => $choices->all(),
        'status' => $status,
        'apply' => $canApply && ! $status && $choices->isNotEmpty() && ! $ownRoleOf->has($expert->expert_id),
        // Stars team leaders gave them after each phase, averaged.
        'rating' => $expertRatings[$expert->expert_id]['average'] ?? null,
        'ratings' => $expertRatings[$expert->expert_id]['total'] ?? 0,
        'studyLeave' => (bool) $expert->on_study_leave,
        // What earlier teams' leaders wrote, newest first. No team names.
      ];
    }

    $applicationPayloads = $openRequests->mapWithKeys(fn ($a) => [$a->assignment_id => [
      'id' => $a->assignment_id,
      'name' => $a->expert->name,
      'initials' => $a->expert->avatarInitials(),
      'avatar' => $a->expert->hasAvatarImage() ? $a->expert->avatarUrl() : null,
      'role' => $a->engaged_role_name,
      'status' => $a->status,
      'message' => $a->message,
      'reason' => $a->rejection_reason,
      'proposalTitle' => $a->preProposal?->title,
      'proposalFile' => $a->proposal_file ? \Illuminate\Support\Facades\Storage::url($a->proposal_file) : null,
      'sent' => $a->created_at?->format('M j, Y'),
    ]])->all();

    $teamForJs = collect($roleNames)->mapWithKeys(function ($r) use ($filledSlots, $removalByAssignment) {
      $a = $filledSlots[$r] ?? null;

      return [$r => $a ? [
        'expert_id' => (int) $a->expert_id,
        'assignment_id' => (int) $a->assignment_id,
        'name' => $a->expert->name,
        'initials' => $a->expert->avatarInitials(),
        'avatar' => $a->expert->hasAvatarImage() ? $a->expert->avatarUrl() : null,
        'removal_request' => $removalByAssignment->get($a->assignment_id)['request'] ?? null,
      ] : null];
    });

    $pendingByRole = $waitingRequests->mapWithKeys(fn ($a) => [$a->engaged_role_name ?? '' => $a->expert->name])->except('');
    $findUrl = fn (string $role) => route('student.experts', array_filter([
      'tab' => 'find',
      'role' => $role,
      'q' => $search !== '' ? $search : null,
      'skill' => $activeSkill !== '' ? $activeSkill : null,
      'skill_group' => $activeGroup !== '' ? $activeGroup : null,
      'open' => $openOnly ? 1 : null,
    ]));
  @endphp

  @php
    // Your experts tab: what is owed for the latest passed defense, and
    // how much of it the leader has already sent.
    $dueDefense = $evaluationsDue->first();
    $dueRows = $dueDefense ? $evaluationsDue->where('defense_id', $dueDefense->defense_id) : collect();
    $dueDone = $dueDefense ? $evaluationHistory->where('defense_id', $dueDefense->defense_id)->count() : 0;
    $dueTotal = $dueRows->count() + $dueDone;
  @endphp
  <div class="xh" id="xh" data-tab="{{ $tab }}">
    <div class="xh-pagehead">
      <div>
        <h1 class="xh-h1">Experts</h1>
        <p>Book consultations, rate your experts, or find a new one.</p>
      </div>
      <div class="xh-seg" role="tablist" aria-label="Experts page sections">
        <button type="button" role="tab" id="xh-tab-book" aria-controls="xh-panel-book" data-xh="tab" data-tab="consultations" aria-selected="{{ $tab !== 'find' ? 'true' : 'false' }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Your experts
          <span class="xh-count" id="xh-book-count" @if($upcomingBookings->isEmpty()) hidden @endif>{{ $upcomingBookings->count() }}</span>
        </button>
        <button type="button" role="tab" id="xh-tab-find" aria-controls="xh-panel-find" data-xh="tab" data-tab="find" aria-selected="{{ $tab === 'find' ? 'true' : 'false' }}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
          Find experts
        </button>
      </div>
    </div>

    {{-- ===================== YOUR EXPERTS ===================== --}}
    <section id="xh-panel-book" role="tabpanel" aria-labelledby="xh-tab-book" @if($tab !== 'consultations') hidden @endif>
      @if($isLeader && $dueDefense)
        <div class="xh-due">
          <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
          <div class="xh-due-text"><b>{{ $dueDefense->stage }} passed. Rate your {{ $dueTotal }} {{ \Illuminate\Support\Str::plural('expert', $dueTotal) }}.</b>
            <span>{{ $dueDone }} of {{ $dueTotal }} done · Answers stay anonymous.</span></div>
          <button type="button" class="xh-btn xh-gold" data-xh="tab" data-tab="evaluations">Rate experts</button>
        </div>
      @endif

      <div class="xh-stats">
        <div class="xh-card xh-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><div><b>{{ $filledCount }} of 3</b><span>Experts chosen</span></div></div>
        <div class="xh-card xh-stat"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><div><b id="xh-up-count">{{ $upcomingBookings->count() }}</b><span>Upcoming consultations</span></div></div>
        @if($dueDefense)
          <div class="xh-card xh-stat"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><div><b>{{ $dueDone }} of {{ $dueTotal }}</b><span>Experts rated · {{ $dueDefense->stage }}</span></div></div>
        @else
          <div class="xh-card xh-stat"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg><div><b>{{ $evaluationHistory->count() }}</b><span>Evaluations sent</span></div></div>
        @endif
      </div>

      <section class="xh-card xh-mine" aria-labelledby="xh-mine-h">
        <h2 id="xh-mine-h" class="xh-label">Your experts</h2>
        <div class="xh-mine-grid">
          @foreach($roleNames as $r)
            @php
              $a = $filledSlots[$r] ?? null;
              $ex = $a?->expert;
              $removal = $a ? $removalByAssignment->get($a->assignment_id) : null;
              $pendingApp = $waitingRequests->first(fn ($w) => $w->engaged_role_name === $r);
            @endphp
            @if($ex)
              @php $rt = $expertRatings[$ex->expert_id] ?? null; $field = $ex->expertiseFor($r)->first(); @endphp
              <article class="xh-ex-card r-{{ $r }}" data-expert-card="{{ $ex->expert_id }}">
                <div class="xh-ex-head">
                  <span class="xh-avw">
                    <span class="xh-av xh-lg r-{{ $r }}" aria-hidden="true">{{ $ex->avatarInitials() }}@if($ex->hasAvatarImage())<img class="avatar-photo" src="{{ $ex->avatarUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">@endif</span>
                    <i class="xh-dot{{ $ex->is_online ? ' is-on' : '' }}" role="img" aria-label="{{ $ex->is_online ? 'Online now' : 'Offline' }}" title="{{ $ex->is_online ? 'Online now' : 'Offline' }}"></i>
                  </span>
                  <div class="xh-ex-id">
                    <b class="xh-ex-name">{{ $ex->name }}</b>
                    <div class="xh-ex-sub"><b>{{ ucfirst($r) }}</b>@if($field) • {{ $field }}@endif</div>
                    <div class="xh-ex-rate">
                      @if($rt)<span class="xh-star" aria-hidden="true">★</span> <b>{{ \App\Support\EvaluationResults::format((float) $rt['average']) }}</b> <span class="xh-mut">({{ $rt['total'] }} {{ $rt['total'] === 1 ? 'review' : 'reviews' }})</span>@else<span class="xh-mut">No reviews yet</span>@endif
                      <span class="xh-mut">·</span>
                      @if($removal)<span class="xh-t-amber">Change asked</span>@else<span class="xh-t-green">Approved</span>@endif
                    </div>
                  </div>
                </div>
                <div class="xh-ex-stats">
                  <div><span>Consultations</span><b data-sessions-for="{{ $ex->expert_id }}">0 sessions</b></div>
                  <div><span>Next consultation</span><b data-next-for="{{ $ex->expert_id }}">None booked</b></div>
                </div>
                <div class="xh-ex-acts">
                  <button type="button" class="xh-btn xh-gold xh-sm" data-xh="schedule" data-role="{{ $r }}">Schedule</button>
                  <button type="button" class="xh-btn xh-ghost xh-sm" data-xh="tab" data-tab="evaluations">Evaluate</button>
                  <button type="button" class="xh-btn xh-ghost xh-sm" data-xh="profile" data-id="{{ $ex->expert_id }}">Profile</button>
                </div>
              </article>
            @else
              <article class="xh-ex-card is-vacant r-{{ $r }}">
                <div class="xh-ex-head">
                  <span class="xh-av xh-lg is-vacant" aria-hidden="true">?</span>
                  <div class="xh-ex-id">
                    <b class="xh-ex-name xh-t-amber">Not chosen yet</b>
                    <div class="xh-ex-sub"><b>{{ ucfirst($r) }}</b> • {{ $roleBlurbs[$r] }}</div>
                  </div>
                </div>
                @if($pendingApp)
                  <p class="xh-ex-app"><span class="xh-mut">Your application:</span> <b>{{ $pendingApp->expert->name }}</b> · <b class="xh-t-amber">{{ $pendingApp->meet_asked_at ? 'Wants to meet first' : 'Pending' }}</b></p>
                  <div class="xh-ex-acts"><button type="button" class="xh-btn xh-ghost xh-sm" data-xh="app" data-id="{{ $pendingApp->assignment_id }}">View application</button></div>
                @else
                  <p class="xh-ex-app xh-mut">No application out yet.</p>
                  <div class="xh-ex-acts"><a class="xh-btn xh-ghost xh-sm" href="{{ $findUrl($r) }}">Find a {{ $r }}</a></div>
                @endif
              </article>
            @endif
          @endforeach
          @foreach($unslotted as $a)
            <article class="xh-ex-card">
              <div class="xh-ex-head">
                <span class="xh-av xh-lg" aria-hidden="true">{{ $a->expert->avatarInitials() }}@if($a->expert->hasAvatarImage())<img class="avatar-photo" src="{{ $a->expert->avatarUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">@endif</span>
                <div class="xh-ex-id">
                  <b class="xh-ex-name">{{ $a->expert->name }}</b>
                  <div class="xh-ex-sub"><b>Expert</b></div>
                  <div class="xh-ex-rate"><span class="xh-t-green">Approved</span></div>
                </div>
              </div>
              <div class="xh-ex-acts"><button type="button" class="xh-btn xh-ghost xh-sm" data-xh="profile" data-id="{{ $a->expert_id }}">Profile</button></div>
            </article>
          @endforeach
        </div>
      </section>

      <section class="xh-card xh-upc" aria-labelledby="xh-upc-h">
        <div class="xh-upc-head">
          <h2 id="xh-upc-h" class="xh-label">Upcoming consultations</h2>
          <span class="xh-upc-links">
            <button type="button" class="xh-link" data-xh="schedule">All sessions ›</button>
            <button type="button" class="xh-link" data-xh="tab" data-tab="evaluations">Sent evaluations ›</button>
          </span>
        </div>
        <div class="xh-upc-list" id="xh-upcoming"></div>
      </section>

      {{-- The calendar and session list, opened in a dialog by Schedule. --}}
      <div class="xh-book-holder" id="xh-book-holder" hidden>
        <div class="xh-split">
          <div class="xh-card xh-grow xh-book" id="xh-book"></div>
          <aside class="xh-card xh-rail xh-rail-card" id="xh-sessions" aria-label="My consultations"></aside>
        </div>
      </div>
    </section>
    {{-- ===================== FIND EXPERTS ===================== --}}
    <section id="xh-panel-find" role="tabpanel" aria-labelledby="xh-tab-find" @if($tab !== 'find') hidden @endif>
      {{-- Quick guide: open until the team has its first expert, folded after. --}}
      <details class="xh-guide" @if($filledCount === 0) open @endif>
        <summary><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>How expert selection works</summary>
        <ol>
          <li><b>Choose a role</b><span>The one your team still needs.</span></li>
          <li><b>Apply to one expert</b><span>Pick someone with an open slot.</span></li>
          <li><b>Wait for them to accept</b><span>They then show up in Your experts.</span></li>
        </ol>
        <p>To change your adviser, consultant or grammarian later, ask your section's CaPSA.</p>
      </details>
      {{-- The team's three experts. Under Consultations a card picks who you
           book; under Find experts it picks the role you are browsing. --}}
      <section class="xh-strip-sec" aria-labelledby="xh-strip-h">
        <div class="xh-strip-head">
          <h2 id="xh-strip-h">Your team's adviser, consultant and grammarian</h2>
          @php $stillNeed = array_values(array_filter($roleNames, fn ($r) => ! isset($filledSlots[$r]))); @endphp
          <span><b>{{ $filledCount }} of 3</b> chosen<span id="xh-strip-hint">{{ $filledCount === 0 ? ' · start with your adviser' : ($stillNeed ? ' · still need your '.implode(' and ', $stillNeed) : '') }}</span></span>
        </div>
        <div class="xh-strip" id="xh-strip">
          @foreach($roleNames as $r)
            @php
              $a = $filledSlots[$r] ?? null;
              $ex = $a?->expert;
              $removal = $a ? $removalByAssignment->get($a->assignment_id) : null;
              if ($a) {
                $findMeta = $removal ? 'Change request waiting on CaPSA' : 'Accepted your team';
              } elseif ($pendingByRole->has($r)) {
                $findMeta = 'Waiting on '.$pendingByRole[$r];
              } else {
                $n = $roleCounts[$r] ?? 0;
                $findMeta = $n.' '.\Illuminate\Support\Str::plural($r, $n).' to browse';
              }
            @endphp
            <a class="xh-slot r-{{ $r }} role-{{ $r }}{{ $activeRole === $r ? ' is-on' : '' }}{{ $a ? '' : ' is-open' }}" data-slot-role="{{ $r }}" data-xh="slot" data-role="{{ $r }}"
               href="{{ $findUrl($r) }}" aria-current="{{ $activeRole === $r ? 'true' : 'false' }}">
              @if($ex)
                <span class="xh-av r-{{ $r }}" aria-hidden="true">{{ $ex->avatarInitials() }}@if($ex->hasAvatarImage())<img class="avatar-photo" src="{{ $ex->avatarUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">@endif</span>
              @else
                <span class="xh-av is-vacant" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
              @endif
              <span class="xh-slot-body">
                <span class="xh-slot-role">{{ ucfirst($r) }}</span>
                <span class="xh-slot-name">{{ $ex ? $ex->name : 'Not chosen yet' }}</span>
                <span class="xh-slot-meta" data-meta="find">{{ $findMeta }}</span>
                <span class="xh-slot-meta" data-meta="book">{{ $ex ? 'No session booked' : ($pendingByRole->has($r) ? '1 application pending' : 'Not chosen yet') }}</span>
              </span>
              @if($removal)
                <span class="xh-t-amber">Change asked</span>
              @elseif(! $ex)
                <span class="xh-t-gray">Open</span>
              @endif
            </a>
          @endforeach
          @foreach($unslotted as $a)
            <div class="xh-slot" data-slot-role="assigned">
              <span class="xh-av" aria-hidden="true">{{ $a->expert->avatarInitials() }}@if($a->expert->hasAvatarImage())<img class="avatar-photo" src="{{ $a->expert->avatarUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">@endif</span>
              <span class="xh-slot-body">
                <span class="xh-slot-role">Expert</span>
                <span class="xh-slot-name">{{ $a->expert->name }}</span>
                <span class="xh-slot-meta">Accepted your team</span>
              </span>
            </div>
          @endforeach
        </div>
      </section>
      <div class="xh-split">
        <div class="xh-grow xh-find">
          @if(! $team)
            <div class="xh-banner xh-info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg><span class="xh-banner-text">You need to join or create a team before you can apply for an expert.</span></div>
          @elseif(! $isLeader)
            <div class="xh-banner xh-info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg><span class="xh-banner-text">Only your team leader can apply for experts.</span></div>
          @elseif(! $teamConfirmed)
            <div class="xh-banner xh-info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg><span class="xh-banner-text">Your team has to be verified before you can apply for experts.</span></div>
          @elseif(! $approvedProposal)
            <div class="xh-banner xh-info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg><span class="xh-banner-text">Your pre-proposal has to be recommended by your CaPSA before you can apply for experts.</span></div>
          @endif

          @if($activeFilled)
            @php $activeRemoval = $removalByAssignment->get($activeFilled->assignment_id); @endphp
            @if($activeRemoval)
              <div class="xh-banner xh-wait">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                <span class="xh-banner-text">Your request to change <b>{{ $activeFilled->expert->name }}</b> is waiting on your section's CaPSA. They stay your {{ $activeRole }} until the CaPSA decides.@if(! empty($activeRemoval['replacement'])) If approved, your application goes to <b>{{ $activeRemoval['replacement'] }}</b>.@endif</span>
                @if($isLeader)
                  <button type="button" class="xh-btn xh-ghost xh-sm" data-xh="takeback" data-request="{{ $activeRemoval['request'] }}" data-name="{{ $activeFilled->expert->name }}" data-role="{{ $activeRole }}">Take back request</button>
                @endif
              </div>
            @else
              <div class="xh-banner xh-ok">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
                <span class="xh-banner-text"><b>{{ $activeFilled->expert->name }}</b> is your {{ $activeRole }}.</span>
                @if($isLeader)
                  <button type="button" class="xh-btn xh-ghost xh-sm" data-xh="change" data-assignment="{{ $activeFilled->assignment_id }}" data-name="{{ $activeFilled->expert->name }}" data-role="{{ $activeRole }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                    Request a change
                  </button>
                @endif
              </div>
            @endif
          @elseif($activePending && $activePending->meet_asked_at)
            {{-- They asked to meet first: the team books one of their
                 consultation times, and they answer after the meeting. --}}
            @php
              $meetBooked = $activePending->meeting();
              $meetTimes = $meetBooked ? [] : app(\App\Support\ConsultationBoard::class)->openTimes((int) $activePending->expert_id);
            @endphp
            <div class="xh-banner xh-info" id="xh-meet">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
              <span class="xh-banner-text"><b>{{ $activePending->expert?->name }}</b> wants to meet your team first, then answers as your {{ $activeRole }}.
                @if($activePending->meet_note) Their note: “{{ $activePending->meet_note }}”@endif
                @if($meetBooked)
                  <br><b>Meeting booked · {{ \App\Models\TeamExpertAssignment::timeLabel((string) $meetBooked->session_date?->format('Y-m-d'), $meetBooked->schedule?->start_time) }}</b>. It is in your Consultations.
                @elseif(! $isLeader)
                  <br>Your leader books one of their times.
                @elseif(! $meetTimes)
                  <br>They have no open times in the next two weeks. Check again later.
                @else
                  <br>Pick a time from their schedule:
                @endif
              </span>
              @if(! $meetBooked && $isLeader && $meetTimes)
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;flex-basis:100%">
                  @foreach($meetTimes as $t)
                    <button type="button" class="xh-tslot" style="width:auto;min-height:0" data-xh="meet-pick" data-schedule="{{ $t['schedule_id'] }}" data-date="{{ $t['date'] }}" aria-pressed="false">{{ $t['label'] }}</button>
                  @endforeach
                  <button type="button" class="xh-btn xh-gold xh-sm" data-xh="meet-book" disabled>Book this time</button>
                </div>
              @endif
            </div>
          @elseif($activePending)
            <div class="xh-banner xh-wait">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              <span class="xh-banner-text">Your team is waiting on <b>{{ $activePending->expert?->name }}</b> to answer as your {{ $activeRole }}. Once they answer, or you withdraw it, you can apply to another.</span>
              <button type="button" class="xh-btn xh-ghost xh-sm" data-xh="app" data-id="{{ $activePending->assignment_id }}">View application</button>
            </div>
          @endif

          {{-- Searching reloads the page: the name and skill search runs on
               the server, which also puts the best fits first. --}}
          <div class="xh-card xh-filter">
          <form class="filters xh-find-head" method="GET" action="{{ route('student.experts') }}">
            <input type="hidden" name="tab" value="find">
            <input type="hidden" name="role" value="{{ $activeRole }}">
            @if($activeSkill !== '')<input type="hidden" name="skill" value="{{ $activeSkill }}">@endif
            @if($activeGroup !== '')<input type="hidden" name="skill_group" value="{{ $activeGroup }}">@endif
            @if($openOnly)<input type="hidden" name="open" value="1">@endif
            <h2>{{ ucfirst(\Illuminate\Support\Str::plural($activeRole)) }}
              <span class="xh-tally">
                <b>{{ $openCount }}</b> with open slots · <b>{{ $fullCount }}</b> full
                @if($notTakingCount) · <b>{{ $notTakingCount }}</b> not taking teams @endif
                @if($team?->titleTags()) · <b class="is-fit">{{ $fittingIds->count() }}</b> <span class="is-fit">fit your team's tags</span> @endif
              </span>
            </h2>
            <label class="xh-search" for="xh-q">
              {{-- The magnifier is the submit button: with the skill picker's
                   own text box in this form, Enter alone does not submit it. --}}
              <button type="submit" class="xh-search-go" aria-label="Search experts">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
              </button>
              <span class="xh-sr">Search by name or research area</span>
              <input id="xh-q" type="search" name="q" value="{{ $search }}" enterkeyhint="search" placeholder="Name or research area, e.g. Technical Writing" autocomplete="off">
            </label>
            <div class="xh-skill" id="xh-skill">
              <button type="button" class="xh-chip-btn" id="xh-skill-trigger" data-xh="skill-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="xh-skill-pop">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
                <span>{{ $activeSkill !== '' ? $activeSkill : ($activeGroup !== '' ? $activeGroup : 'All skills') }}</span>
              </button>
              <div class="xh-skill-pop" id="xh-skill-pop" hidden>
                <label class="xh-skill-search" for="xh-skill-q">
                  <span class="xh-sr">Search skills</span>
                  <input id="xh-skill-q" type="text" placeholder="Search skills…" autocomplete="off">
                </label>
                <div class="xh-skill-list" id="xh-skill-list">
                  <a class="xh-skill-opt{{ $activeSkill === '' && $activeGroup === '' ? ' is-on' : '' }}" data-all href="{{ route('student.experts', ['tab' => 'find', 'role' => $activeRole]) }}">All skills</a>
                  @forelse($skillGroups as $groupName => $groupSkills)
                    <details class="xh-skill-group" data-name="{{ mb_strtolower($groupName) }}" @if($activeGroup === $groupName) open @endif>
                      <summary><span>{{ $groupName }}</span><span class="xh-skill-n">{{ $groupSkills->count() }}</span></summary>
                      <a class="xh-skill-opt xh-skill-any{{ $activeGroup === $groupName && $activeSkill === '' ? ' is-on' : '' }}" data-name="{{ mb_strtolower($groupName) }}" href="{{ route('student.experts', ['tab' => 'find', 'role' => $activeRole, 'skill_group' => $groupName]) }}">Anything in this area</a>
                      @foreach($groupSkills as $skill)
                        <a class="xh-skill-opt{{ mb_strtolower($activeSkill) === mb_strtolower($skill->skill_name) ? ' is-on' : '' }}" data-name="{{ mb_strtolower($skill->skill_name) }}" href="{{ route('student.experts', ['tab' => 'find', 'role' => $activeRole, 'skill' => $skill->skill_name]) }}">
                          <span>{{ $skill->skill_name }}</span><span class="xh-skill-n">{{ $skill->expert_count }}</span>
                        </a>
                      @endforeach
                    </details>
                  @empty
                    <p class="xh-skill-none">No skills recorded yet. They appear here once experts fill in their expertise.</p>
                  @endforelse
                  <p class="xh-skill-none" id="xh-skill-nomatch" hidden>No skill matches that.</p>
                </div>
              </div>
            </div>
            @if($activeSkill !== '' || $activeGroup !== '')
              <a class="xh-chip-x" href="{{ route('student.experts', array_filter(['tab' => 'find', 'role' => $activeRole, 'q' => $search ?: null, 'open' => $openOnly ? 1 : null])) }}" aria-label="Clear skill filter">Clear skill</a>
            @endif
          </form>
          <div class="xh-apps" role="group" aria-labelledby="xh-apps-h">
            <span class="xh-label" id="xh-apps-h">Your applications</span>
            @if($waitingRequests->isNotEmpty())
              <span class="xh-rail-sub">{{ $waitingRequests->count() }} {{ \Illuminate\Support\Str::plural('request', $waitingRequests->count()) }} waiting on a reply</span>
            @endif
            @forelse($openRequests as $a)
              @php $tone = $a->status === 'pending' ? 'amber' : 'red'; @endphp
              <button type="button" class="app-item xh-app" data-xh="app" data-id="{{ $a->assignment_id }}">
                <span class="xh-av xh-sm r-{{ $a->engaged_role_name ?? 'adviser' }}" aria-hidden="true">{{ $a->expert->avatarInitials() }}@if($a->expert->hasAvatarImage())<img class="avatar-photo" src="{{ $a->expert->avatarUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">@endif</span>
                <span class="xh-app-meta">
                  <b>{{ $a->expert->name }}</b>
                  <span>{{ $a->status === 'pending' && $a->meet_asked_at ? 'Wants to meet first' : ($a->engaged_role_name ? 'Applied as '.$a->engaged_role_name : 'Application') }} · {{ $a->created_at?->format('M j') }}</span>
                </span>
                <span class="xh-t-{{ $tone }}">{{ $a->status === 'pending' ? 'Pending' : 'Declined' }}</span>
              </button>
            @empty
              <span class="xh-mut">No requests out yet.</span>
            @endforelse
          </div>
          </div>

          @if($shownExperts->isEmpty())
            <div class="xh-card xh-hint xh-empty-list">
              @if($activeSkill !== '' || $activeGroup !== '')
                <span>No {{ $activeRole }} lists a skill matching <strong>{{ $activeSkill !== '' ? $activeSkill : $activeGroup }}</strong>.</span>
                <a class="xh-btn xh-ghost xh-sm" href="{{ route('student.experts', ['tab' => 'find', 'role' => $activeRole]) }}">Show all experts</a>
              @elseif($search !== '')
                <span>No {{ $activeRole }} matches “{{ $search }}”.</span>
                <a class="xh-btn xh-ghost xh-sm" href="{{ route('student.experts', ['tab' => 'find', 'role' => $activeRole]) }}">Clear search and filters</a>
              @elseif($openOnly)
                <span>No {{ \Illuminate\Support\Str::plural($activeRole) }} have open slots right now.</span>
                <a class="xh-btn xh-ghost xh-sm" href="{{ route('student.experts', ['tab' => 'find', 'role' => $activeRole]) }}">Show everyone</a>
              @else
                <span>No {{ \Illuminate\Support\Str::plural($activeRole) }} are available yet.</span>
              @endif
            </div>
          @else
            {{-- Open experts on one side, the rest on the other, a page of
                 cards at a time; the script below hides the cards that are
                 not on the current page. --}}
            <div class="xh-results" id="xh-results">
              <div class="xh-bar">
                <div class="xh-side" role="group" aria-label="Show">
                  <button type="button" data-xh="side" data-side="open" aria-pressed="true">Open slots<small>{{ $openCount }}</small></button>
                  <button type="button" data-xh="side" data-side="closed" aria-pressed="false" @disabled($openCount === $shownExperts->count())>Full or not taking<small>{{ $shownExperts->count() - $openCount }}</small></button>
                </div>
                <div class="xh-pgnav" id="xh-pgnav" hidden>
                  <span class="xh-dots" id="xh-pg-dots" aria-hidden="true"></span>
                  <span class="xh-keys" title="Keyboard: the left and right arrow keys flip pages"><kbd>&larr;</kbd><kbd>&rarr;</kbd> keys</span>
                  <button type="button" class="xh-pg" data-xh="page" data-dir="-1" aria-label="Previous page (left arrow key)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg></button>
                  <span id="xh-pg-range"></span>
                  <button type="button" class="xh-pg" data-xh="page" data-dir="1" aria-label="Next page (right arrow key)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></button>
                </div>
              </div>
              <div class="xh-card xh-hint" id="xh-side-empty" hidden>No {{ $activeRole }} here has an open slot. Check <b>Full or not taking</b>.</div>
            <div class="xh-grid">
              @foreach($shownExperts as $expert)
                @php
                  $p = $expertPayloads[$expert->expert_id];
                  $also = collect($p['roles'])->reject(fn ($r) => $r === $activeRole);
                  $isFit = in_array($expert->expert_id, $fitIds, true);

                  // Skills that cover the team's tags first, then as many as
                  // fit in about two lines; the rest become "+N more".
                  $cardTags = [];
                  $tagRoom = 80;
                  foreach (collect($p['tags'])->sortByDesc(fn ($t) => in_array($t, $expert->matching_fields, true)) as $tag) {
                    if ($cardTags && mb_strlen($tag) + 4 > $tagRoom) { break; }
                    $cardTags[] = $tag;
                    $tagRoom -= mb_strlen($tag) + 4;
                  }
                @endphp
                <article class="xh-card xh-xcard r-{{ $activeRole }}{{ $isFit ? ' xh-fit' : '' }}{{ $expert->is_open ? '' : ' is-closed' }}" data-expert-id="{{ $expert->expert_id }}" data-side="{{ $expert->is_open ? 'open' : 'closed' }}">
                  <div class="xh-xhead">
                    <span class="xh-av xh-lg r-{{ $activeRole }}" aria-hidden="true">{{ $p['initials'] }}@if($p['avatar'])<img class="avatar-photo" src="{{ $p['avatar'] }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">@endif</span>
                    <div>
                      <h3>{{ $expert->name }}</h3>
                      <div class="xh-rating">
                        @if($p['ratings'] > 0)
                          <span class="xh-stars" role="img" aria-label="Rated {{ $p['rating'] }} out of 5">@for($s = 1; $s <= 5; $s++)<svg viewBox="0 0 24 24" class="{{ $s <= round($p['rating']) ? 'is-on' : '' }}" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>@endfor</span>
                          <b>{{ \App\Support\EvaluationResults::format((float) $p['rating']) }}<small>/5</small></b><span class="xh-rating-n">{{ number_format($p['ratings']) }} {{ $p['ratings'] === 1 ? 'review' : 'reviews' }}</span>
                        @else
                          <span class="xh-rating-n">No reviews yet</span>
                        @endif
                      </div>
                      @if($isFit || $also->isNotEmpty())
                        <div class="xh-chips">
                          @if($isFit)<span class="xh-fitmark" title="Fits your team: {{ implode(', ', $expert->matched_tags) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>Fits your team</span>@endif
                          @foreach($also as $r)<span class="xh-chip r-{{ $r }}">also {{ $r }}</span>@endforeach
                        </div>
                      @endif
                    </div>
                  </div>
                  <div class="xh-sec">
                    <span class="xh-lbl">Research area as {{ \App\Models\ExpertExpertise::SIDES[$activeRole] ?? ucfirst($activeRole) }}</span>
                    <div class="xh-tags">
                      @forelse($cardTags as $tag)
                        <span class="xh-tag{{ $expert->is_open && in_array($tag, $expert->matching_fields, true) ? ' is-match' : '' }}">{{ $tag }}</span>
                      @empty
                        <span class="xh-tag">Not filled in yet</span>
                      @endforelse
                      @if(count($p['tags']) > count($cardTags))<span class="xh-tag">+{{ count($p['tags']) - count($cardTags) }} more</span>@endif
                    </div>
                  </div>
                  <div class="xh-sec">
                    @php
                      $capFull = $p['max'] > 0 && $p['used'] >= $p['max'];
                      $capPct = $p['max'] > 0 ? min(100, (int) round($p['used'] / $p['max'] * 100)) : 0;
                    @endphp
                    <div class="xh-caprow"><span>Availability</span>
                      <b class="{{ $p['max'] < 1 ? 'is-unset' : ($capFull ? 'is-full' : 'is-ok') }}">
                        @if($p['max'] < 1) Capacity not set
                        @elseif($capFull) Full · {{ $p['used'] }} of {{ $p['max'] }} teams
                        @else {{ $p['max'] - $p['used'] }} of {{ $p['max'] }} slots open
                        @endif
                      </b>
                    </div>
                    <div class="xh-meter{{ $capFull ? ' is-full' : '' }}"><i style="width:{{ $capPct }}%"></i></div>
                    @if($p['studyLeave'])
                      {{-- Quiet on purpose: worth knowing before applying, not a warning. --}}
                      <p class="xh-leave"><span class="xh-leave-tag">Study leave</span> May be slow to reply and hard to book.</p>
                    @endif
                  </div>
                  <div class="xh-xact">
                    <button type="button" class="xh-viewlink" data-xh="profile" data-id="{{ $expert->expert_id }}">View profile <span aria-hidden="true">→</span></button>
                    @if($p['apply'])
                      <button type="button" class="xh-btn xh-gold" data-xh="apply" data-apply-expert="{{ $expert->expert_id }}" data-id="{{ $expert->expert_id }}">Apply</button>
                    @elseif($p['status'])
                      <span class="xh-xstatus xh-t-{{ $p['status']['tone'] }}">{{ $p['status']['text'] }}</span>
                    @endif
                  </div>
                </article>
              @endforeach
            </div>
            </div>
          @endif
        </div>

      </div>
    </section>

    {{-- ===================== EVALUATIONS ===================== --}}
    {{-- After each passed defense the leader answers the CaPSA's questions about
         every expert they cover, 1-5 each; the rating is the average. Anonymous
         to the CaPSA and the expert. See App\Support\ExpertEvaluations. --}}
    @php
      $evalExperts = $assignments->where('status', 'approved')->pluck('expert')->filter()->keyBy('expert_id');
      $evalByDefense = $evaluationsDue->groupBy('defense_id');
      $scale = \App\Models\EvaluationTemplate::SCALE;
      $star = '<svg viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
      $starsOf = fn (float $r) => collect(range(1, 5))->map(fn ($s) => '<svg viewBox="0 0 24 24" class="'.($s <= round($r) ? 'is-on' : '').'" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>')->implode('');
      $fmtRating = fn ($r) => \App\Support\EvaluationResults::format((float) $r);
    @endphp
    <section id="xh-panel-eval" role="tabpanel" aria-labelledby="xh-tab-book" @if($tab !== 'evaluations') hidden @endif>
      <nav class="xh-crumb" aria-label="Breadcrumb"><button type="button" class="xh-link" data-xh="tab" data-tab="consultations">Your experts</button> <span aria-hidden="true">›</span> <b>Rate your experts</b></nav>
      @if(session('evaluation_saved'))
        <div class="xh-banner xh-ok xh-ev-flash" role="status"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg><span class="xh-banner-text">{{ session('evaluation_saved') }}</span></div>
      @endif
      @if(session('evaluation_error') || ($errors->any() && old('form_key')))
        <div class="xh-banner xh-wait xh-ev-flash" role="alert"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg><span class="xh-banner-text">{{ session('evaluation_error') ?? $errors->first() }}</span></div>
      @endif

      @if(! $team)
        <div class="xh-banner xh-info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg><span class="xh-banner-text">Join or create a team first. After each defense your team passes, your leader evaluates your experts here.</span></div>
      @else
      <div class="xh-split">
        <div class="xh-grow xh-ev">
          <div class="xh-ev-head">
            <h2>To evaluate</h2>
            <span>{{ $evaluationsDue->count() }} waiting · once per defense: Pre-proposal, Title, Pre-oral and Final</span>
          </div>

          <div class="xh-ev-anon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
            <span><b>Anonymous.</b> Your CaPSA and the expert see the answers, never who sent them. Answer honestly: 1 is Poor, 5 is Excellent.</span>
          </div>

          @if($evaluationsDue->isEmpty())
            <div class="xh-card xh-hint xh-ev-empty">
              {!! str_replace('<svg ', '<svg fill="none" stroke="currentColor" stroke-width="1.8" ', $star) !!}
              <b>Nothing to evaluate right now</b>
              <p>When your team passes a defense, {{ $isLeader ? 'you answer' : 'your leader answers' }} your CaPSA's questions about your adviser, consultant and grammarian here. The next phase opens once it is done.</p>
            </div>
          @elseif(! $isLeader)
            <div class="xh-banner xh-info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg><span class="xh-banner-text">Your team leader evaluates the experts. <b>{{ $evaluationsDue->count() }}</b> {{ $evaluationsDue->count() === 1 ? 'evaluation is' : 'evaluations are' }} waiting for {{ $evalByDefense->map(fn ($rows) => $rows->first()->stage)->implode(', ') }}.</span></div>
          @else
            @foreach($evalByDefense as $defenseId => $rows)
              @php
                $isOld = old('form_key') === (string) $defenseId;
                $rows = $rows->filter(fn ($r) => $evaluationTemplates->has($r->template_id))->values();
                $totalQs = $rows->sum(fn ($r) => $evaluationTemplates->get($r->template_id)->questions->count());
              @endphp
              @continue($rows->isEmpty())
              {{-- One sheet per defense: each expert a card of 1-5 rows, all sent together. --}}
              <form class="xh-card xh-ev-phase xh-ev-form" method="POST" action="{{ route('student.evaluations.store_all') }}" novalidate data-stage="{{ $rows->first()->stage }}">
                @csrf
                <input type="hidden" name="form_key" value="{{ $defenseId }}">
                <input type="hidden" name="defense_id" value="{{ $defenseId }}">
                <header class="xh-ev-phase-head">
                  <div>
                    <span class="xh-ev-kicker">Defense passed</span>
                    <h3>Rate your experts — {{ $rows->first()->stage }}</h3>
                    <p class="xh-ev-sub">Open one expert at a time and pick one circle per row.</p>
                  </div>
                  <span class="xh-ev-done"><b data-done>0</b> of {{ $rows->count() }} {{ $rows->count() === 1 ? 'expert' : 'experts' }} done</span>
                </header>
                @foreach($rows as $i => $row)
                  @php
                    $key = $row->expert_id.'-'.$row->role;
                    $ex = $evalExperts->get($row->expert_id);
                    $template = $evaluationTemplates->get($row->template_id);
                    $next = $rows->get($i + 1);
                  @endphp
                  <details class="xh-ex" data-expert="{{ $row->expert }}" data-role="{{ $row->role_label }}" @if($i === 0) open @endif>
                    <summary>
                      <span class="xh-ex-num" aria-hidden="true">{{ $i + 1 }}</span>
                      <span class="xh-av r-{{ $row->role }}" aria-hidden="true">{{ $ex?->avatarInitials() }}@if($ex?->hasAvatarImage())<img class="avatar-photo" src="{{ $ex->avatarUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">@endif</span>
                      <span class="xh-ex-who"><b>{{ $row->expert }}</b> · {{ $row->role_label }}</span>
                      <span class="xh-ex-status" data-status>Not started</span>
                      <svg class="xh-ex-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <input type="hidden" name="evaluations[{{ $key }}][expert_id]" value="{{ $row->expert_id }}">
                    <input type="hidden" name="evaluations[{{ $key }}][role]" value="{{ $row->role }}">
                    <div class="xh-ex-body">
                      <table class="xh-ex-grid">
                        <thead>
                          <tr>
                            <th scope="col">Question</th>
                            @for($s = 1; $s <= 5; $s++)
                              <th scope="col">{{ $s }}<span>{{ $scale[$s] }}</span></th>
                            @endfor
                          </tr>
                        </thead>
                        <tbody>
                          @foreach($template->questions as $q)
                            @php $picked = $isOld ? (int) old('evaluations.'.$key.'.answers.'.$q->id) : 0; @endphp
                            <tr class="xh-ev-q">
                              <th scope="row">{{ $q->question }}<span class="xh-ex-miss">Not answered</span></th>
                              @for($s = 1; $s <= 5; $s++)
                                <td><label><input type="radio" name="evaluations[{{ $key }}][answers][{{ $q->id }}]" value="{{ $s }}" aria-label="{{ $q->question }}: {{ $s }} · {{ $scale[$s] }}" @checked($picked === $s)><span class="xh-ex-dot" aria-hidden="true"></span></label></td>
                              @endfor
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                      @if($next)
                        <div class="xh-ex-next"><button type="button" class="xh-btn xh-gold xh-sm" data-next>Next: {{ $next->expert }} ›</button></div>
                      @endif
                    </div>
                  </details>
                @endforeach
                <div class="xh-ev-foot">
                  <span class="xh-ev-left" data-left><b>0</b> of {{ $totalQs }} questions answered</span>
                  <button type="submit" class="xh-btn xh-gold">Send all {{ $rows->count() }} {{ $rows->count() === 1 ? 'evaluation' : 'evaluations' }}</button>
                </div>
              </form>
            @endforeach
          @endif
        </div>

        <aside class="xh-card xh-rail xh-rail-card xh-ev-history" aria-labelledby="xh-ev-history-h">
          <div class="xh-ev-head">
            <h2 id="xh-ev-history-h">Sent evaluations</h2>
            <span>{{ $evaluationHistory->count() }}</span>
          </div>
          @forelse($evaluationHistory as $ev)
            <div class="xh-ev-sent r-{{ $ev->role }}">
              <div class="xh-ev-sent-top">
                <b>{{ $ev->expert?->name ?? 'Former expert' }}</b>
                <span class="xh-ev-score"><span class="xh-stars" role="img" aria-label="{{ $fmtRating($ev->rating) }} out of 5">{!! $starsOf((float) $ev->rating) !!}</span>{{ $fmtRating($ev->rating) }}</span>
              </div>
              <div class="xh-ev-sent-meta">{{ \App\Models\ExpertEvaluation::ROLES[$ev->role] ?? ucfirst($ev->role) }} · {{ $ev->defense?->defense_type ?? 'Earlier phase' }} · {{ $ev->created_at?->format('M j, Y') }}</div>
              @if($ev->answers)
                <details class="xh-ev-answers">
                  <summary>Your answers</summary>
                  <ul>
                    @foreach($ev->answers as $a)
                      <li><span>{{ $a['question'] ?? '' }}</span><b>{{ $a['score'] ?? '' }}</b></li>
                    @endforeach
                  </ul>
                </details>
              @elseif($ev->comment)
                <p>{{ $ev->comment }}</p>
              @endif
            </div>
          @empty
            <p class="xh-ev-none">No evaluations sent yet.</p>
          @endforelse
        </aside>
      </div>
      @endif
    </section>
  </div>

  @if($approvedProposal)
    {{-- The recommended pre-proposal, read before it goes out with an application. --}}
    <template id="xh-proposal">
      <div class="xh-dlg-head">
        <div class="xh-ttl"><span class="xh-eyebrow">Recommended pre-proposal</span><h2>{{ $approvedProposal->title }}</h2></div>
        <button type="button" class="xh-x" data-xh="close" aria-label="Close"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
      </div>
      <div class="xh-dlg-body">
        @foreach([
          'Problem statement' => $approvedProposal->problem_statement,
          'Proposed solution' => $approvedProposal->proposed_solution,
          'Scope' => $approvedProposal->scope,
          'Limitations' => $approvedProposal->limitations,
          'Software development method' => $approvedProposal->sdm,
          'Clients/Organization' => $approvedProposal->clients,
          'Address of the organization' => $approvedProposal->client_address,
          'Representative' => $approvedProposal->client_representative,
          'Their position' => $approvedProposal->client_position,
          'Research agenda' => \App\Support\ResearchAgenda::items($approvedProposal->research_agenda),
          'SDG goals' => $approvedProposal->sdg_goals,
        ] as $label => $value)
          @if(filled($value))
            <div class="xh-pp"><h3>{{ $label }}</h3><p>{{ is_array($value) ? implode(', ', $value) : $value }}</p></div>
          @endif
        @endforeach
      </div>
      <div class="xh-dlg-foot">
        <button type="button" class="xh-btn xh-ghost" data-xh="close">Close</button>
        @if($proposalPdfUrl)
          <button type="button" class="xh-btn xh-gold" data-xh="pdf">View original PDF</button>
        @endif
      </div>
    </template>
  @endif
  </div>
</div>

@php
  $xhData = [
  'tab' => $tab,
  'activeRole' => $activeRole,
  'team' => $teamForJs,
  'consultExperts' => $consultExperts->values(),
  'defaultExpertId' => $defaultExpertId,
  'slots' => $availableSlots->values(),
  'full' => $fullOccurrences,
  'windows' => $bookingWindows,
  'bookedExpertIds' => $bookedExpertIds,
  'upcoming' => $upcomingBookings,
  'recent' => $recentBookings,
  'pendingByRole' => $pendingByRole,
  'deliverableOptions' => $deliverableOptions ?? [],
  'cutoffDays' => \App\Http\Controllers\StudentConsultationController::CANCEL_CUTOFF_DAYS,
  'reasonMin' => \App\Models\ExpertRemovalRequest::REASON_MIN,
  'replacementOptions' => $replacementOptions,
  'teamSection' => $teamSection,
  'isLeader' => $isLeader,
  'hasApprovedProposal' => (bool) $approvedProposal,
  'proposalTitle' => $approvedProposal?->title,
  'proposalPdfUrl' => $proposalPdfUrl,
  'findUrl' => route('student.experts'),
  'urls' => [
    'refresh' => route('student.consultation.refresh'),
    'book' => route('student.consultation.book'),
    'cancel' => url('/student/consultation/cancel'),
    'apply' => route('api.student.assignments.store'),
    'assignments' => url('/api/assignments'),
    'removals' => url('/api/expert-removal-requests'),
  ],
];
@endphp
<script nonce="{{ Vite::cspNonce() }}">
window.XH_DATA = @json($xhData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
window.XH_EXPERTS = {{ Js::from((object) $expertPayloads) }};
window.XH_APPS = @json((object) $applicationPayloads);
</script>
<script nonce="{{ Vite::cspNonce() }}">
(function () {
'use strict';

var D = window.XH_DATA;
var EXPERTS = window.XH_EXPERTS || {};
var APPS = window.XH_APPS || {};
var CSRF = document.querySelector('meta[name="csrf-token"]').content;
var ROLES = ['adviser', 'consultant', 'grammarian'];
var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
var DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
var MONTHS_AHEAD = 6;

var slots = D.slots || [];
var full = new Set(D.full || []);
var windows = D.windows || {};
var bookedExpertIds = (D.bookedExpertIds || []).map(Number);
var upcoming = D.upcoming || [];
var recent = D.recent || [];

var S = {
  tab: D.tab,
  role: firstBookableRole(),
  monthOffset: 0,
  date: null,
  slotId: null,
  listTab: 'upcoming',
  showAll: false,
  draft: null,
  topics: []
};

/* ---------------- helpers ---------------- */
function $(id) { return document.getElementById(id); }
function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
function ucfirst(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }
function pad(n) { return String(n).padStart(2, '0'); }
function keyOf(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }
function parseKey(k) { var p = String(k).split('-').map(Number); return new Date(p[0], p[1] - 1, p[2]); }
function todayStart() { var t = new Date(); t.setHours(0, 0, 0, 0); return t; }
function daysUntil(k) { return k ? Math.round((parseKey(k) - todayStart()) / 86400000) : null; }
function fmtT(t) { if (!t) { return ''; } var p = String(t).split(':').map(Number); return (p[0] % 12 || 12) + ':' + pad(p[1]) + ' ' + (p[0] < 12 ? 'AM' : 'PM'); }
function shortDate(k) { var d = parseKey(k); return DAYS[d.getDay()].slice(0, 3) + ', ' + MONTHS[d.getMonth()].slice(0, 3) + ' ' + d.getDate(); }
function longDate(k) { var d = parseKey(k); return DAYS[d.getDay()] + ', ' + MONTHS[d.getMonth()] + ' ' + d.getDate(); }
function lengthOf(s) {
  if (!s.start_time || !s.end_time) { return ''; }
  var m = function (t) { var p = t.split(':').map(Number); return p[0] * 60 + p[1]; };
  var mins = m(s.end_time) - m(s.start_time);
  if (mins <= 0) { return ''; }
  return mins % 60 === 0 ? (mins / 60) + (mins === 60 ? ' hour' : ' hours') : mins + ' mins';
}
function firstName(n) { return String(n || '').trim().split(/\s+/)[0] || 'your expert'; }
function initialsOf(n) { return String(n || '').trim().split(/\s+/).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join(''); }
function words(s) { s = String(s || '').trim(); return s ? s.split(/\s+/).length : 0; }
function toast(message, type) {
  if (window.ctToast) { return window.ctToast(message, type || 'success'); }
  var t = document.createElement('div');
  t.className = 'xh-toast' + (type === 'error' ? ' is-error' : '');
  t.setAttribute('role', type === 'error' ? 'alert' : 'status');
  t.textContent = message;
  document.body.appendChild(t);
  setTimeout(function () { t.remove(); }, 4500);
}
function avatar(p, role, size) {
  return '<span class="xh-av ' + (size || '') + (role ? ' r-' + role : '') + '" aria-hidden="true">' + esc(p.initials || '')
    + (p.avatar ? '<img class="avatar-photo" src="' + esc(p.avatar) + '" alt="" referrerpolicy="no-referrer" onerror="this.remove()">' : '') + '</span>';
}

var ICON = {
  x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>',
  check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
  info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
  clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>',
  lock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
  left: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>',
  right: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>',
  cal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
  file: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg>',
  swap: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>',
  trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>',
  user: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M19 8v6M22 11h-6"/></svg>'
};

/* ---------------- who can be booked ---------------- */
function expertFor(role) {
  return (D.consultExperts || []).filter(function (e) { return (e.roles || []).indexOf(role) !== -1; })[0] || null;
}
function hasAnyExpert() { return (D.consultExperts || []).length > 0; }
function firstBookableRole() {
  var def = (D.consultExperts || []).filter(function (e) { return e.expert_id === D.defaultExpertId; })[0];
  if (def) { return def.roles[0]; }
  for (var i = 0; i < ROLES.length; i++) { if (expertFor(ROLES[i])) { return ROLES[i]; } }
  return D.activeRole || 'adviser';
}
function roleOfExpert(id) {
  var e = (D.consultExperts || []).filter(function (x) { return Number(x.expert_id) === Number(id); })[0];
  return e ? e.roles[0] : null;
}

/* ---------------- calendar data (as the old Consultation page read it) ---------------- */
function withinWindow(expertId, dateKey, startTime) {
  var w = windows[expertId];
  if (!w) { return true; }
  if (dateKey + ' ' + String(startTime || '00:00').slice(0, 5) < w.earliest) { return false; }
  return !w.latest || dateKey <= w.latest;
}
function matchesDate(s, dayName, dateKey) {
  if (s.recur === 'once') { return s.specific_date === dateKey; }
  if (s.available_date !== dayName) { return false; }
  // Every other week: from its first date, every second week only.
  if (s.recur === 'biweekly' && s.specific_date) {
    var gap = Math.round((parseKey(dateKey) - parseKey(String(s.specific_date).slice(0, 10))) / 86400000);
    return gap >= 0 && gap % 14 === 0;
  }
  return true;
}
function hasEnded(dateKey, endTime) {
  var n = new Date();
  var nowKey = keyOf(n.getFullYear(), n.getMonth(), n.getDate()) + ' ' + pad(n.getHours()) + ':' + pad(n.getMinutes());
  return dateKey + ' ' + String(endTime || '23:59').slice(0, 5) <= nowKey;
}
/** Every slot on that date the team could see: open ones, and ones already taken. */
function slotsOn(expertId, dateKey) {
  var dayName = DAYS[parseKey(dateKey).getDay()];
  return slots.filter(function (s) {
    return Number(s.expert_id) === Number(expertId) && matchesDate(s, dayName, dateKey)
      && !hasEnded(dateKey, s.end_time) && withinWindow(expertId, dateKey, s.start_time);
  }).map(function (s) {
    return { slot: s, taken: !s.is_available || full.has(s.schedule_id + '|' + dateKey) };
  }).sort(function (a, b) { return String(a.slot.start_time).localeCompare(String(b.slot.start_time)); });
}
function bookingOn(expertId, dateKey) {
  return upcoming.concat(recent).some(function (b) { return b.session_date === dateKey && Number(b.expert_id) === Number(expertId); });
}
function liveBookingWith(expertId) {
  return upcoming.filter(function (b) { return Number(b.expert_id) === Number(expertId); })[0] || null;
}
/** The next dates with an open time, from today on, as far as the calendar goes. */
function nextOpenDates(expertId, howMany) {
  var found = [];
  var day = todayStart();
  var end = new Date(day.getFullYear(), day.getMonth() + MONTHS_AHEAD + 1, 0);
  while (day <= end && found.length < howMany) {
    var k = keyOf(day.getFullYear(), day.getMonth(), day.getDate());
    var open = slotsOn(expertId, k).filter(function (x) { return !x.taken; }).length;
    if (open) { found.push({ key: k, open: open }); }
    day.setDate(day.getDate() + 1);
  }
  return found;
}
function monthOffsetOf(dateKey) {
  var d = parseKey(dateKey);
  var now = new Date();
  return (d.getFullYear() - now.getFullYear()) * 12 + d.getMonth() - now.getMonth();
}

/* ---------------- render: shared ---------------- */
function render() {
  var book = S.tab === 'consultations';
  $('xh').dataset.tab = S.tab;
  // Two tabs; the rating sheet belongs to Your experts.
  ['book', 'find'].forEach(function (t) {
    var on = (S.tab === 'find') === (t === 'find');
    $('xh-tab-' + t).setAttribute('aria-selected', String(on));
    $('xh-tab-' + t).tabIndex = on ? 0 : -1;
  });
  $('xh-panel-book').hidden = !book;
  $('xh-panel-find').hidden = S.tab !== 'find';
  $('xh-panel-eval').hidden = S.tab !== 'evaluations';
  var count = $('xh-book-count');
  count.textContent = upcoming.length;
  count.hidden = upcoming.length === 0;
  $('xh-up-count').textContent = upcoming.length;
  var filled = ROLES.filter(function (r) { return D.team[r]; }).length;
  var hint = $('xh-strip-hint');
  var need = ROLES.filter(function (r) { return !D.team[r]; });
  if (hint) { hint.textContent = filled === 0 ? ' · start with your adviser' : need.length ? ' · still need your ' + need.join(' and ') : ''; }
  renderStrip();
  if (book) { renderMine(); renderUpcoming(); }
  if (S.sched) { renderBook(); renderSessions(); }
}

// Each expert card: how many sessions so far, and the next one.
function renderMine() {
  document.querySelectorAll('[data-sessions-for]').forEach(function (el) {
    var id = Number(el.dataset.sessionsFor);
    var n = upcoming.concat(recent).filter(function (b) { return Number(b.expert_id) === id; }).length;
    el.textContent = n + (n === 1 ? ' session' : ' sessions');
  });
  document.querySelectorAll('[data-next-for]').forEach(function (el) {
    var nx = liveBookingWith(Number(el.dataset.nextFor));
    el.textContent = nx && nx.session_date ? shortDate(nx.session_date) + ' · ' + fmtT(nx.start_time) : 'None booked';
  });
}

function renderUpcoming() {
  var box = $('xh-upcoming');
  box.innerHTML = upcoming.length ? upcoming.map(function (b) {
    var p = EXPERTS[b.expert_id] || { initials: initialsOf(b.expert_name) };
    return '<div class="xh-upc-row">' + avatar(p, roleOfExpert(b.expert_id), 'xh-sm')
      + '<span class="xh-upc-who"><b>' + esc(b.expert_name) + '</b><span>' + (b.session_date ? shortDate(b.session_date) + ' · ' : '') + fmtT(b.start_time) + ' · ' + esc(b.slot_type || 'Online') + '</span></span>'
      + (b.cancel_requested ? '<b class="xh-t-amber">Cancel asked</b>' : '<b class="xh-t-green">Scheduled</b>')
      + '<button type="button" class="xh-btn xh-ghost xh-sm" data-xh="session" data-id="' + b.booking_id + '">Details</button></div>';
  }).join('') : '<p class="xh-hint">Nothing booked yet. Press Schedule on an expert to pick a date.</p>';
}

function renderStrip() {
  var book = S.tab === 'consultations';
  var strip = $('xh-strip');
  ROLES.forEach(function (r) {
    var card = strip.querySelector('[data-role="' + r + '"]');
    if (!card) { return; }
    var on = book ? S.role === r : D.activeRole === r;
    card.classList.toggle('is-on', on);
    card.setAttribute('aria-current', String(on));
    var ex = expertFor(r);
    if (ex) {
      var nx = liveBookingWith(ex.expert_id);
      card.querySelector('[data-meta="book"]').textContent = nx && nx.session_date
        ? 'Next: ' + shortDate(nx.session_date) + ' · ' + fmtT(nx.start_time)
        : 'No session booked';
    }
  });
  var on = strip.querySelector('.is-on');
  if (on && strip.scrollWidth > strip.clientWidth) {
    strip.scrollLeft += on.getBoundingClientRect().left - strip.getBoundingClientRect().left;
  }
}

/* ---------------- render: consultations ---------------- */
function renderBook() {
  var card = $('xh-book');
  var role = S.role;
  if (!hasAnyExpert()) {
    var waiting = Object.keys(D.pendingByRole || {}).length;
    card.innerHTML = '<div class="xh-empty"><span class="xh-dlg-icon xh-ic-gold xh-empty-ic">' + ICON.cal + '</span>'
      + '<h2>No consultations yet</h2>'
      + '<p>You book consultations with the experts who accepted your team. Your team doesn\'t have one yet'
      + (waiting ? ', and ' + waiting + (waiting === 1 ? ' application is' : ' applications are') + ' still waiting for an answer.' : '.') + '</p>'
      + '<ol class="xh-steps xh-empty-steps">'
      + '<li><i>1</i><span>Find an adviser, consultant and grammarian.</span></li>'
      + '<li><i>2</i><span>Your team leader applies with your pre-proposal.</span></li>'
      + '<li><i>3</i><span>Once your adviser or consultant accepts, their open dates show up here.</span></li></ol>'
      + '<a class="xh-btn xh-gold" href="' + esc(D.findUrl + '?tab=find') + '">Find experts</a></div>';
    return;
  }
  var ex = expertFor(role);
  if (!ex) {
    var p = (D.pendingByRole || {})[role];
    card.innerHTML = '<div class="xh-empty"><span class="xh-av is-vacant xh-empty-av" aria-hidden="true">' + ICON.user + '</span>'
      + '<h2>You don\'t have a ' + role + ' yet</h2>'
      + '<p>Consultations are booked with the experts who accepted your team. '
      + (p ? 'Your application to <b>' + esc(p) + '</b> is still waiting for an answer.' : 'Apply to one, and their open dates show up here once they accept.') + '</p>'
      + '<a class="xh-btn xh-gold" href="' + esc(D.findUrl + '?tab=find&role=' + role) + '">Find a ' + role + '</a></div>';
    return;
  }

  var base = new Date(new Date().getFullYear(), new Date().getMonth() + S.monthOffset, 1);
  var y = base.getFullYear();
  var m = base.getMonth();
  var startDow = base.getDay();
  var dim = new Date(y, m + 1, 0).getDate();
  var total = Math.ceil((startDow + dim) / 7) * 7;
  var live = liveBookingWith(ex.expert_id) || (bookedExpertIds.indexOf(Number(ex.expert_id)) !== -1 ? {} : null);

  // Nobody hunts for a gold date: the nearest open one in the month on screen
  // is already picked, and a month with none says where the next one is.
  var firstOpen = null;
  if (!live) {
    for (var fd = 1; fd <= dim && !firstOpen; fd++) {
      var fk = keyOf(y, m, fd);
      if (daysUntil(fk) >= 0 && !bookingOn(ex.expert_id, fk) && slotsOn(ex.expert_id, fk).some(function (x) { return !x.taken; })) { firstOpen = fk; }
    }
    if (!S.date) { S.date = firstOpen; }
  }

  var cells = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'].map(function (d) { return '<span class="xh-dow" aria-hidden="true">' + d + '</span>'; });
  for (var i = 0; i < total; i++) {
    var d = i - startDow + 1;
    if (d < 1 || d > dim) { cells.push('<span class="xh-day is-blank" aria-hidden="true"></span>'); continue; }
    var k = keyOf(y, m, d);
    var off = daysUntil(k);
    var list = off >= 0 ? slotsOn(ex.expert_id, k) : [];
    var open = list.filter(function (x) { return !x.taken; }).length;
    var mine = bookingOn(ex.expert_id, k);
    var sel = S.date === k;
    var cls = 'xh-day';
    var cap = '';
    var capShort = null;
    var disabled = true;
    // Open dates are gold tiles. A date the expert does not take is crossed
    // out. While the team already has a session with this expert, the open
    // dates stay visible, outlined, so the team can see when they are free.
    var aria = '';
    if (off < 0) { cls += ' is-past'; }
    else if (mine) { cls += ' is-booked'; cap = 'Booked'; capShort = ''; aria = 'your booking'; }
    else if (!list.length) { cls += off === 0 ? '' : ' is-off'; aria = 'not offered'; }
    else if (open === 0) { cls += ' is-full'; cap = 'Full'; aria = 'fully booked'; }
    else if (live) { cls += ' is-locked'; cap = open + ' open'; capShort = String(open); aria = open + ' open, book after your current session'; }
    else { cls += ' is-avail'; cap = open + ' open'; capShort = String(open); disabled = false; aria = open + ' open'; }
    if (off === 0) { cls += ' is-today'; if (!cap) { cap = 'Today'; } }
    cells.push('<button type="button" class="' + cls + '" data-xh="date" data-key="' + k + '"' + (disabled ? ' disabled' : '')
      + ' aria-pressed="' + sel + '" aria-label="' + longDate(k) + (aria ? ', ' + aria : '') + '">'
      + '<span class="xh-dn">' + d + '</span><span class="xh-dc"><span class="xh-cap is-long">' + cap + '</span><span class="xh-cap is-short">' + (capShort === null ? cap : capShort) + '</span></span></button>');
  }

  var times;
  if (S.date && !live) {
    var ts = slotsOn(ex.expert_id, S.date);
    times = '<h3>' + longDate(S.date) + '</h3>' + (ts.length ? ts.map(function (x) {
      var on = S.slotId === x.slot.schedule_id;
      var st = x.taken ? 'Taken' : 'Book';
      return '<button type="button" class="xh-tslot" data-xh="slot" data-id="' + x.slot.schedule_id + '"' + (x.taken ? ' disabled' : '') + ' aria-pressed="' + (on && !x.taken) + '"'
        + ' aria-label="' + (x.taken ? 'Taken: ' : 'Book ') + fmtT(x.slot.start_time) + ' to ' + fmtT(x.slot.end_time) + '">'
        + '<span class="xh-tt"><b>' + fmtT(x.slot.start_time) + ' – ' + fmtT(x.slot.end_time) + '</b><span class="xh-note">' + (lengthOf(x.slot) || 'Consultation') + '</span></span>'
        + '<span class="xh-st">' + st + '</span></button>';
    }).join('') : '<div class="xh-hint">No open times on this date.</div>');
  } else if (live) {
    times = '<h3>Available times</h3><div class="xh-hint">One booking per expert at a time. You can pick a new time after your '
      + (live.session_date ? shortDate(live.session_date) + ' ' : '') + 'session.</div>';
  } else {
    times = '<h3>Available times</h3><div class="xh-hint">Pick a <b class="xh-gold-text">gold date</b> to see its open times.</div>';
  }
  if (S.date && !live) {
    times += '<p class="xh-tap-hint">Tap a time to book it.</p>';
  }

  var notice = '';
  if (live) {
    notice = '<div class="xh-banner xh-info xh-book-notice">' + ICON.info + '<span class="xh-banner-text">You already have a session with <b>' + esc(ex.name) + '</b>'
      + (live.session_date ? ' on <b>' + shortDate(live.session_date) + ' at ' + fmtT(live.start_time) + '</b>. You can book them again after ' + shortDate(live.session_date) + '.' : '. You can book them again after it.') + ' Their open dates are outlined below.</span>'
      + (live.booking_id ? '<button type="button" class="xh-btn xh-ghost xh-sm" data-xh="session" data-id="' + live.booking_id + '">View session</button>' : '') + '</div>';
  }

  var monthEmpty = '';
  if (!live && !firstOpen) {
    var later = nextOpenDates(ex.expert_id, 1).filter(function (o) { return monthOffsetOf(o.key) > S.monthOffset; })[0];
    monthEmpty = later
      ? '<div class="xh-month-empty"><span>No open dates in ' + MONTHS[m] + '. Next open: <b>' + shortDate(later.key) + '</b></span>'
        + '<button type="button" class="xh-btn xh-ghost xh-sm" data-xh="jump" data-key="' + later.key + '">Go to it</button></div>'
      : '<div class="xh-month-empty"><span>' + esc(ex.name) + ' has no open dates in the next ' + (MONTHS_AHEAD + 1) + ' months yet. New ones show up here as soon as they add them.</span></div>';
  }

  card.innerHTML = '<div class="xh-book-head"><div><h2>Book with ' + esc(ex.name) + '</h2>'
    + '<p>' + ucfirst(role) + ' · Only the dates and times they opened can be booked.</p></div>'
    + '<div class="xh-month"><button type="button" class="xh-icon-btn" data-xh="month" data-dir="-1" aria-label="Previous month"' + (S.monthOffset <= 0 ? ' disabled' : '') + '>' + ICON.left + '</button>'
    + '<b aria-live="polite">' + MONTHS[m] + ' ' + y + '</b>'
    + '<button type="button" class="xh-icon-btn" data-xh="month" data-dir="1" aria-label="Next month"' + (S.monthOffset >= MONTHS_AHEAD ? ' disabled' : '') + '>' + ICON.right + '</button></div></div>'
    + notice
    + '<div class="xh-book-cols"><div class="xh-cal">' + monthEmpty + '<div class="xh-cal-grid">' + cells.join('') + '</div>'
    + '<div class="xh-legend"><span><i class="xh-lg-sq is-open"></i>Open</span><span><i class="xh-lg-sq is-mine"></i>Your booking</span>'
    + '<span><i class="xh-lg-sq is-full"></i>Fully booked</span><span><i class="xh-lg-sq is-off"></i>Not offered</span></div></div>'
    + '<div class="xh-times">' + times + '</div></div>';
}

function canCancel(b) { var n = daysUntil(b.session_date); return n !== null && n >= D.cutoffDays && !b.cancel_requested; }
function lastCancelDay(b) { var d = parseKey(b.session_date); d.setDate(d.getDate() - D.cutoffDays); return shortDate(keyOf(d.getFullYear(), d.getMonth(), d.getDate())); }

function sessionCard(b, isPast, isNext) {
  var role = roleOfExpert(b.expert_id);
  var foot = '';
  var badge;
  if (isPast) {
    badge = '<span class="xh-t-gray">Ended</span>';
  } else if (b.cancel_requested) {
    badge = '<span class="xh-t-amber">Cancel asked</span>';
    foot = '<span class="xh-sess-foot">' + ICON.clock + 'Waiting for ' + esc(firstName(b.expert_name)) + ' to answer</span>';
  } else {
    var n = daysUntil(b.session_date);
    badge = '<span class="xh-t-' + (isNext ? 'goldp' : 'green') + '">' + (n === null ? 'Scheduled' : n <= 0 ? 'Today' : n === 1 ? 'Tomorrow' : 'In ' + n + ' days') + '</span>';
    foot = canCancel(b)
      ? '<span class="xh-sess-foot">' + ICON.clock + 'You can cancel until ' + lastCancelDay(b) + '</span>'
      : '<span class="xh-sess-foot">' + ICON.lock + 'Within ' + D.cutoffDays + ' days, so it can\'t be cancelled here</span>';
  }
  var dt = b.session_date ? parseKey(b.session_date) : null;
  return '<button type="button" class="xh-sess' + (isNext ? ' is-next' : '') + '" data-xh="session" data-id="' + b.booking_id + '" data-booking-id="' + b.booking_id + '">'
    + '<span class="xh-dblock' + (isPast ? ' is-past' : '') + '"><small>' + (dt ? MONTHS[dt.getMonth()].slice(0, 3).toUpperCase() : '--') + '</small><b>' + (dt ? dt.getDate() : '--') + '</b><span>' + (dt ? DAYS[dt.getDay()].slice(0, 3) : '') + '</span></span>'
    + '<span class="xh-sess-body"><span class="xh-sess-top"><b>' + fmtT(b.start_time) + (b.end_time ? ' – ' + fmtT(b.end_time) : '') + '</b>' + badge + '</span>'
    + '<span class="xh-sess-with">Consultation with ' + esc(b.expert_name) + (role ? ' · ' + ucfirst(role) : '') + '</span>'
    + (b.topics ? '<span class="xh-sess-about">About ' + esc(b.topics) + '</span>' : '') + foot + '</span></button>';
}

function renderSessions() {
  var aside = $('xh-sessions');
  aside.hidden = !hasAnyExpert() && !upcoming.length && !recent.length;
  if (aside.hidden) { return; }
  var showUp = S.listTab === 'upcoming';
  var all = showUp ? upcoming : recent;
  var list = S.showAll ? all : all.slice(0, 2);
  var more = all.length > 2 ? '<button type="button" class="xh-btn xh-ghost xh-sm" data-xh="show-all">' + (S.showAll ? 'Show fewer' : 'Show all ' + all.length) + '</button>' : '';
  aside.innerHTML = '<h2>My consultations</h2>'
    + '<div class="xh-mini-seg" role="tablist" aria-label="Which consultations">'
    + '<button type="button" role="tab" data-xh="list" data-list="upcoming" aria-selected="' + showUp + '">Upcoming <span class="xh-n">' + upcoming.length + '</span></button>'
    + '<button type="button" role="tab" data-xh="list" data-list="past" aria-selected="' + !showUp + '">Past <span class="xh-n">' + recent.length + '</span></button></div>'
    + '<div class="xh-list">' + (list.length ? list.map(function (b, i) { return sessionCard(b, !showUp, showUp && i === 0); }).join('')
      : '<div class="xh-hint">' + (showUp ? 'Nothing booked yet. Pick a date on the calendar.' : 'No past consultations yet.') + '</div>') + more + '</div>'
    + '<p class="xh-rail-note">One live booking per expert. A booking can\'t be cancelled once the session is within ' + D.cutoffDays + ' days; message your expert instead.</p>';
}

/* ---------------- dialogs ---------------- */
/*
 * Every pop-up here is the CapsTrack modal (partials/modal): 'ask' is the
 * small box in the middle for a yes or no, anything else a panel from the
 * right ('wide' for the long forms). On a phone both are a sheet from the
 * bottom.
 */
function openDialog(html, shape, onClose) {
  var d = document.createElement('dialog');
  d.className = 'cm xh-dlg ' + (shape === 'ask' ? 'cm-ask' : 'cm-panel' + (shape === 'wide' ? ' cm-wide' : ''));
  d.innerHTML = '<div class="cm-box">' + html + '</div>';
  document.body.appendChild(d);
  // A form that asks for one thing opens on it; one with a choice first does not.
  var first = d.querySelector('[data-autofocus]') || (d.querySelector('.xh-topics') ? null : d.querySelector('textarea, input[type="text"]'));
  window.CapsModal.open(d, { dismissible: true, focus: first, onClose: function () { d.remove(); if (onClose) { onClose(); } } });
  return d;
}
function head(title, sub, icon, eyebrow) {
  return '<div class="xh-dlg-head">' + (icon || '') + '<div class="xh-ttl">' + (eyebrow ? '<span class="xh-eyebrow">' + eyebrow + '</span>' : '')
    + '<h2>' + title + '</h2>' + (sub ? '<p>' + sub + '</p>' : '') + '</div>'
    + '<button type="button" class="xh-x" data-xh="close" aria-label="Close">' + ICON.x + '</button></div>';
}
function closeDialog(d) { if (d) { window.CapsModal.close(d); } }
function closeAll() { document.querySelectorAll('dialog.xh-dlg').forEach(closeDialog); }
function busy(btn, label) { btn.disabled = true; btn.dataset.label = btn.innerHTML; btn.textContent = label; }
function idle(btn) { btn.disabled = false; if (btn.dataset.label) { btn.innerHTML = btn.dataset.label; } }
function reloadOnFind() {
  var url = new URL(window.location.href);
  url.searchParams.set('tab', 'find');
  url.searchParams.delete('apply');
  setTimeout(function () { window.location.href = url.toString(); }, 1100);
}
function send(url, method, body, isForm) {
  var headers = { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' };
  if (body && !isForm) { headers['Content-Type'] = 'application/json'; }
  return fetch(url, { method: method, headers: headers, body: body ? (isForm ? body : JSON.stringify(body)) : undefined })
    .then(function (r) { return r.json().catch(function () { return { success: false, message: 'Something went wrong (' + r.status + '). Try again.' }; }); });
}

// One block per team slot, the way the expert sees it on their own profile.
function slotsHtml(p) {
  if (p.max < 1) { return '<div class="xh-prof-slots"><b>Not taking teams yet</b><span>No team limit set</span></div>'; }
  var open = Math.max(p.max - p.used, 0), cells = '';
  for (var i = 0; i < Math.max(p.max, p.used); i++) { cells += i < p.used ? '<span class="' + (i < p.max ? 'is-taken' : 'is-over') + '"></span>' : '<span></span>'; }
  return '<div class="xh-prof-slots"><b>' + (open === 0 ? 'No team slots open' : open + (open === 1 ? ' team slot open' : ' team slots open')) + '</b>'
    + '<span>' + (p.max < p.used ? 'Limit ' + p.max + ', handling ' + p.used : p.used + ' of ' + p.max + ' taken') + '</span></div>'
    + '<div class="xh-prof-meter" role="img" aria-label="' + p.used + ' of ' + p.max + ' team slots taken">' + cells + '</div>';
}
// The same card the expert sees as "What students see" on their profile page.
function openProfile(id) {
  var p = EXPERTS[id];
  if (!p) { return; }
  var role = p.roles.indexOf(D.activeRole) !== -1 ? D.activeRole : p.roles[0];
  var action = p.apply
    ? '<button type="button" class="xh-btn xh-gold" data-xh="apply" data-id="' + p.id + '">Apply</button>'
    : (p.status ? '<span class="xh-t-' + p.status.tone + ' xh-pill-center">' + esc(p.status.text) + '</span>' : '');
  openDialog('<div class="xh-prof-head">' + avatar(p, role, 'xh-lg') + '<div class="xh-ttl"><h2>' + esc(p.profileName) + '</h2>'
    + (p.email ? '<div class="xh-prof-email">' + esc(p.email) + '</div>' : '') + '</div>'
    + '<button type="button" class="xh-x" data-xh="close" aria-label="Close">' + ICON.x + '</button></div>'
    + '<div class="xh-dlg-body xh-prof-body"><div class="xh-chips">'
    + (p.roles.length ? p.roles.map(function (r) { return '<span class="xh-chip r-' + r + '">' + ucfirst(r) + '</span>'; }).join('') : '<span class="xh-chip">No role yet</span>') + '</div>'
    + '<div>' + slotsHtml(p) + '</div>'
    + '<div><div class="xh-prof-sub">Research areas as ' + esc(p.side) + '</div><div class="xh-tags">'
    + (p.tags.length ? p.tags.map(function (t) { return '<span class="xh-tag">' + esc(t) + '</span>'; }).join('') : '<span class="xh-prof-none">None yet</span>') + '</div></div>'
    + (p.studyLeave ? '<p class="xh-leave"><span class="xh-leave-tag">Study leave</span> May be slow to reply and hard to book. Plan sessions early.</p>' : '')
    + '<div class="xh-prof-rating"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>'
    + (p.ratings ? '<b>' + Number(p.rating).toFixed(1) + '</b> from ' + p.ratings + (p.ratings === 1 ? ' team leader rating' : ' team leader ratings') : 'No ratings from teams yet') + '</div></div>'
    + '<div class="xh-dlg-foot"><button type="button" class="xh-btn xh-ghost" data-xh="close">Close</button>' + action + '</div>');
}

function openApply(id, draft) {
  var p = EXPERTS[id];
  if (!p || !p.apply) { return; }
  var pick = draft ? draft.roleId : (p.choices.length === 1 ? p.choices[0].expert_role_id : null);
  if (!pick) {
    var match = p.choices.filter(function (c) { return c.name === D.activeRole; })[0];
    if (match) { pick = match.expert_role_id; }
  }
  var pickedRole = (p.choices.filter(function (c) { return Number(c.expert_role_id) === Number(pick); })[0] || {}).name;
  var roles = p.choices.map(function (c) {
    return '<label class="xh-rp r-' + c.name + '"><input type="radio" name="xh-apply-role" value="' + c.expert_role_id + '" data-name="' + c.name + '"' + (Number(pick) === Number(c.expert_role_id) ? ' checked' : '') + '>'
      + '<span>' + esc(ucfirst(c.name)) + '</span></label>';
  }).join('');
  var proposal = '<div class="xh-field"><span class="xh-flabel">Recommended pre-proposal</span><div class="xh-attach">' + ICON.file
    + '<div class="xh-attach-meta"><b>' + esc(D.proposalTitle) + '</b><span>Recommended by your CaPSA. It is sent with this application, so there is nothing to upload.</span></div>'
    + '<button type="button" class="xh-btn xh-ghost xh-sm" data-xh="proposal">Preview</button></div></div>';
  openDialog(head('Apply to ' + esc(p.name), 'Your application goes to ' + esc(p.name) + '. They accept or decline.')
    + '<div class="xh-dlg-body" data-expert="' + p.id + '">'
    + '<fieldset class="xh-field" id="xh-f-role"><legend class="xh-flabel">Apply as <span class="xh-req">*</span></legend>'
    + '<div class="xh-roles-pick">' + roles + '</div>'
    + '<span class="xh-note">An expert can take only one role per team. Once they are your adviser, you can\'t also engage them as your consultant or grammarian.</span>'
    + '<span class="xh-fmeta"><span class="xh-ferr">Choose the role you are applying for.</span></span></fieldset>'
    + proposal
    + '<div class="xh-field" id="xh-f-msg"><label for="xh-apply-msg"><span id="xh-msg-to">' + (pickedRole ? 'Message to your ' + esc(pickedRole) : 'Message to ' + esc(p.name)) + '</span> <span class="xh-req">*</span></label>'
    + '<textarea id="xh-apply-msg" rows="5" placeholder="Introduce your team and say why you are applying to ' + esc(p.name) + '.">' + esc(draft ? draft.message : '') + '</textarea>'
    + '<span class="xh-fmeta"><span class="xh-ferr" id="xh-msg-err">Write a short message to ' + esc(p.name) + '.</span><span><span id="xh-msg-count">' + words(draft ? draft.message : '') + '</span>/500 words</span></span></div></div>'
    + '<div class="xh-dlg-foot"><button type="button" class="xh-btn xh-ghost" data-xh="close">Cancel</button><button type="button" class="xh-btn xh-gold" data-xh="apply-review">Review application</button></div>', 'wide');
}

function openReview(d) {
  var p = EXPERTS[d.expertId];
  openDialog(head('Review your application', 'Check the details before it goes to ' + esc(p.name) + '.')
    + '<div class="xh-dlg-body"><dl class="xh-rows">'
    + '<div class="xh-row"><dt>Expert</dt><dd>' + esc(p.name) + '</dd></div>'
    + '<div class="xh-row"><dt>Applying as</dt><dd>' + esc(ucfirst(d.roleName)) + '</dd></div>'
    + '<div class="xh-row"><dt>Proposal</dt><dd>' + esc(D.proposalTitle) + '</dd></div>'
    + '<div class="xh-row"><dt>Message</dt><dd>' + esc(d.message) + '</dd></div></dl></div>'
    + '<div class="xh-dlg-foot"><button type="button" class="xh-btn xh-ghost" data-xh="apply-edit">Edit</button><button type="button" class="xh-btn xh-gold" data-xh="apply-send">' + ICON.check + 'Send application</button></div>');
}

function openProposal() {
  var tpl = $('xh-proposal');
  if (!tpl) { return; }
  var d = openDialog('', 'wide');
  d.querySelector('.cm-box').appendChild(tpl.content.cloneNode(true));
}

/* A PDF picker that looks like part of the form: the real input is under the whole box. */
function filePicker(id, chosen) {
  return '<label class="xh-file' + (chosen ? ' is-set' : '') + '" for="' + id + '">'
    + '<input type="file" id="' + id + '" class="xh-file-input" accept=".pdf,application/pdf">'
    + '<span class="xh-file-ic" aria-hidden="true">' + ICON.file + '</span>'
    + '<span class="xh-file-text"><b data-file-name>' + (chosen ? esc(chosen) : 'Choose a PDF') + '</b>'
    + '<span data-file-meta>' + (chosen ? 'Attached · choose again to replace it' : 'PDF only, up to 5 MB') + '</span></span>'
    + '<span class="xh-btn xh-ghost xh-sm xh-file-btn" aria-hidden="true">' + (chosen ? 'Replace' : 'Browse') + '</span></label>';
}
function syncFilePicker(input) {
  var box = input.closest('.xh-file');
  if (!box) { return; }
  var f = input.files && input.files[0];
  var bad = !!f && (!/\.pdf$/i.test(f.name) || f.size > 5 * 1024 * 1024);
  if (bad) { input.value = ''; f = null; }
  box.classList.toggle('is-set', !!f);
  var field = box.closest('.xh-field');
  if (field) { field.classList.toggle('is-bad', bad); }
  box.querySelector('[data-file-name]').textContent = f ? f.name : 'Choose a PDF';
  box.querySelector('[data-file-meta]').textContent = f ? (f.size < 1024 * 1024 ? Math.max(1, Math.round(f.size / 1024)) + ' KB' : (f.size / 1048576).toFixed(1) + ' MB') + ' · PDF' : 'PDF only, up to 5 MB';
  box.querySelector('.xh-file-btn').textContent = f ? 'Replace' : 'Browse';
}

function openChange(btn) {
  var role = btn.dataset.role;
  var options = (D.replacementOptions || {})[role] || [];
  // Type a name, pick from the matches, like adding a recipient in Gmail.
  var picker = options.length
    ? '<div class="xh-ta" data-role="' + esc(role) + '"><input type="hidden" id="xh-change-to" value="">'
      + '<input type="text" id="xh-change-q" role="combobox" aria-expanded="false" aria-controls="xh-ta-list" aria-autocomplete="list" autocomplete="off" placeholder="Type a name">'
      + '<div class="xh-ta-picked" id="xh-ta-picked" hidden></div>'
      + '<div class="xh-ta-list" id="xh-ta-list" role="listbox" hidden></div></div>'
    :'<p class="xh-note xh-note-warn">No other ' + esc(role) + ' has a free slot for your team right now, so a change cannot be asked for yet.</p>';
  var file = D.hasApprovedProposal ? ''
    : '<div class="xh-field" id="xh-f-change-file"><label for="xh-change-file">Pre-proposal for them (PDF) <span class="xh-req">*</span></label>'
      + filePicker('xh-change-file', '') + '<span class="xh-fmeta"><span class="xh-ferr">That is not a PDF, or it is over 5 MB.</span></span></div>';
  openDialog(head('Request a ' + esc(role) + ' change', 'Your section\'s CaPSA decides. ' + esc(btn.dataset.name) + ' stays with your team until they do. Once approved, your application goes to the ' + esc(role) + ' you pick below.',
      '<span class="xh-dlg-icon xh-ic-amber">' + ICON.swap + '</span>')
    + '<div class="xh-dlg-body"><div class="xh-detail r-' + role + '">' + avatar(D.team[role] || {}, role, 'xh-sm') + '<span class="xh-detail-meta"><b>' + esc(btn.dataset.name) + '</b><span>' + ucfirst(role) + ' of your team'
    + (D.teamSection ? ' · Decided by the CaPSA for ' + esc(D.teamSection) : '') + '</span></span></div>'
    + '<div class="xh-field" id="xh-f-reason"><label for="xh-change-reason">Reason for your CaPSA <span class="xh-req">*</span></label>'
    + '<textarea id="xh-change-reason" rows="3" maxlength="500" placeholder="Explain to your CaPSA why your ' + esc(role) + ' has to change."></textarea>'
    + '<span class="xh-fmeta"><span class="xh-ferr">Give at least ' + D.reasonMin + ' characters so your CaPSA can decide.</span><span><span id="xh-reason-count">0</span>/500</span></span></div>'
    + '<div class="xh-field" id="xh-f-change-to"><label for="' + (options.length ? 'xh-change-q' : 'xh-change-to') + '">' + ucfirst(role) + ' to replace them <span class="xh-req">*</span></label>' + picker
    + '<span class="xh-note">Sent to them only if the CaPSA approves and they still have a slot then. They accept or decline your team.</span></div>'
    + (options.length ? '<div class="xh-field" id="xh-f-change-msg"><label for="xh-change-msg">Message to them <span class="xh-req">*</span></label>'
      + '<textarea id="xh-change-msg" rows="3" maxlength="3000" placeholder="Introduce your team and your project to the ' + esc(role) + ' you picked."></textarea></div>' + file : '')
    + '</div>'
    + '<div class="xh-dlg-foot"><button type="button" class="xh-btn xh-ghost" data-xh="close">Cancel</button><button type="button" class="xh-btn xh-gold" data-xh="change-send" data-assignment="' + btn.dataset.assignment + '" id="xh-change-send" disabled>Send to CaPSA</button></div>');
}

function taRow(o, q) {
  var name = esc(o.name);
  var at = q ? o.name.toLowerCase().indexOf(q.toLowerCase()) : -1;
  if (at !== -1) { name = esc(o.name.slice(0, at)) + '<u>' + esc(o.name.slice(at, at + q.length)) + '</u>' + esc(o.name.slice(at + q.length)); }
  var meta = [o.field, o.slots, o.rating ? '★ ' + Number(o.rating).toFixed(1) : '', o.studyLeave ? 'Study leave' : ''].filter(Boolean).map(esc).join(' · ');
  return avatar(o, '', 'xh-sm') + '<span class="xh-ta-meta"><b>' + name + '</b><span>' + meta + '</span></span>';
}

function taOptions() {
  return (D.replacementOptions || {})[document.querySelector('.xh-ta').dataset.role] || [];
}

function taSearch(q) {
  var list = $('xh-ta-list');
  var hits = taOptions().filter(function (o) { return o.name.toLowerCase().indexOf(q.toLowerCase()) !== -1; }).slice(0, 6);
  list.innerHTML = hits.length
    ? hits.map(function (o, i) { return '<button type="button" class="xh-ta-opt" role="option" aria-selected="' + (i === 0) + '" data-xh="ta-pick" data-id="' + o.id + '">' + taRow(o, q) + '</button>'; }).join('')
    : '<span class="xh-ta-none">No match with a free slot.</span>';
  list.hidden = !q;
  $('xh-change-q').setAttribute('aria-expanded', String(!!q));
}

function taPick(id) {
  var o = taOptions().filter(function (x) { return String(x.id) === String(id); })[0];
  $('xh-change-to').value = o ? o.id : '';
  $('xh-ta-picked').innerHTML = o ? taRow(o, '') + '<button type="button" class="xh-link" data-xh="ta-clear">Change</button>' : '';
  $('xh-ta-picked').hidden = !o;
  $('xh-change-q').hidden = !!o;
  $('xh-ta-list').hidden = true;
  $('xh-change-q').setAttribute('aria-expanded', 'false');
  if (!o) { $('xh-change-q').value = ''; $('xh-change-q').focus(); }
  $('xh-change-send').disabled = !changeReady();
}

/* Send is open once every required part of the change request is there. */
function changeReady() {
  var reason = $('xh-change-reason');
  var to = $('xh-change-to');
  var msg = $('xh-change-msg');
  var file = $('xh-change-file');
  return !!reason && reason.value.trim().length >= D.reasonMin
    && !!to && !!to.value
    && !!msg && msg.value.trim() !== ''
    && (!file || (file.files && file.files.length > 0));
}

function openTakeBack(btn) {
  openDialog(head('Take back your change request?', 'Your request to change <b>' + esc(btn.dataset.name) + '</b> is still waiting on your section\'s CaPSA. Taking it back keeps them as your ' + esc(btn.dataset.role || 'expert') + '.',
      '<span class="xh-dlg-icon xh-ic-amber">' + ICON.swap + '</span>')
    + '<div class="xh-dlg-foot is-plain"><button type="button" class="xh-btn xh-ghost" data-xh="close">Keep the request</button><button type="button" class="xh-btn xh-gold" data-xh="takeback-send" data-request="' + btn.dataset.request + '">Take it back</button></div>', 'ask');
}

function openApp(id) {
  var a = APPS[id];
  if (!a) { return; }
  var status = a.status === 'pending' ? '<span class="xh-t-amber">Pending · waiting for ' + esc(a.name) + (a.role ? ' (' + esc(ucfirst(a.role)) + ')' : '') + '</span>' : '<span class="xh-t-red">Declined</span>';
  var proposal = a.proposalTitle ? esc(a.proposalTitle) + ' <span class="xh-note">(recommended pre-proposal)</span>'
    : (a.proposalFile ? '<a href="' + esc(a.proposalFile) + '" target="_blank" rel="noopener" data-pdf-view="' + esc(a.proposalFile) + '" data-pdf-title="Proposal">View proposal</a>' : 'None attached');
  var withdraw = '';
  if (D.isLeader) {
    withdraw = a.status === 'pending'
      ? '<button type="button" class="xh-btn xh-danger-ghost xh-push" data-xh="app-withdraw" data-id="' + a.id + '">' + ICON.trash + 'Withdraw</button>'
      : '<button type="button" class="xh-btn xh-danger-ghost xh-push" data-xh="app-withdraw" data-id="' + a.id + '">' + ICON.trash + 'Remove from list</button>';
  }
  openDialog(head('Application to ' + esc(a.name), a.sent ? 'Sent ' + esc(a.sent) : '')
    + '<div class="xh-dlg-body"><dl class="xh-rows">'
    + '<div class="xh-row"><dt>Status</dt><dd>' + status + '</dd></div>'
    + (a.role ? '<div class="xh-row"><dt>Applied as</dt><dd>' + esc(ucfirst(a.role)) + '</dd></div>' : '')
    + '<div class="xh-row"><dt>Proposal</dt><dd>' + proposal + '</dd></div>'
    + '<div class="xh-row"><dt>Your message</dt><dd>' + esc(a.message || '') + '</dd></div>'
    + (a.reason ? '<div class="xh-row"><dt>Their reason</dt><dd>' + esc(a.reason) + '</dd></div>' : '') + '</dl></div>'
    + '<div class="xh-dlg-foot">' + withdraw + '<button type="button" class="xh-btn xh-ghost" data-xh="close">Close</button></div>');
}

function openWithdraw(id) {
  var a = APPS[id];
  var pending = a.status === 'pending';
  openDialog(head(pending ? 'Withdraw this application?' : 'Remove this from your list?',
      pending ? esc(a.name) + ' will no longer see it. You can apply to them again later.' : 'The declined application to ' + esc(a.name) + ' leaves your list. Nothing is sent to them.',
      '<span class="xh-dlg-icon xh-ic-red">' + ICON.trash + '</span>')
    + '<div class="xh-dlg-foot is-plain"><button type="button" class="xh-btn xh-ghost" data-xh="close">Keep it</button><button type="button" class="xh-btn xh-danger" data-xh="app-withdraw-send" data-id="' + a.id + '">' + (pending ? 'Withdraw application' : 'Remove from list') + '</button></div>', 'ask');
}

var DELIVS = ['Chapter 1', 'Chapter 2', 'Chapter 3', 'Chapter 4', 'Chapter 5', 'Chapters 1–3, together', 'Chapters 1–5, together',
  'Final manuscript', 'Logical diagram (DFD or system flowchart)', 'Prototype', 'Collection instrument (survey, interview)'];
var TICK = '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';

function slotCard(dateKey, line, sub) {
  var d = parseKey(dateKey);
  return '<div class="xh-slotc"><span class="xh-datet" aria-hidden="true"><small>' + MONTHS[d.getMonth()].slice(0, 3).toUpperCase() + '</small><b>' + d.getDate() + '</b></span>'
    + '<span class="xh-slott"><b>' + line + '</b><span>' + sub + '</span></span></div>';
}

/* Tapping a time opens this: what the team will bring, a note, and Book. */
function openBookConfirm() {
  var ex = expertFor(S.role);
  var s = slots.filter(function (x) { return x.schedule_id === S.slotId; })[0];
  if (!ex || !s || !S.date) { return; }
  var n = daysUntil(S.date);
  var first = firstName(ex.name);
  openDialog(head('Book this time', 'Pick what you\'ll bring so ' + esc(first) + ' can prepare.', '', 'Book a consultation')
    + '<div class="xh-dlg-body">'
    + slotCard(S.date, DAYS[parseKey(S.date).getDay()] + ' · ' + fmtT(s.start_time) + ' – ' + fmtT(s.end_time), 'with ' + esc(ex.name) + ', ' + ucfirst(S.role))
    + '<div class="xh-field" id="xh-f-deliv"><span class="xh-flabel" id="xh-deliv-h">Which deliverables? <span class="xh-req">*</span> <span class="xh-opt">Pick one or more</span></span>'
    + '<details class="xh-dd" id="xh-deliv-dd"><summary aria-describedby="xh-deliv-h"><span id="xh-deliv-sum" class="xh-mut">Choose deliverables</span></summary>'
    + '<div class="xh-dd-list">' + DELIVS.map(function (t) {
      return '<label class="xh-dd-item"><input type="checkbox" data-deliv value="' + esc(t) + '"><span>' + esc(t) + '</span></label>';
    }).join('') + '<label class="xh-dd-item"><input type="checkbox" id="xh-deliv-others"><span>Others</span></label></div></details>'
    + '<input type="text" id="xh-deliv-other" maxlength="112" autocomplete="off" placeholder="Please specify" aria-label="Other deliverable" hidden>'
    + '<span class="xh-fmeta"><span class="xh-ferr" id="xh-deliv-err">Pick at least one.</span></span></div>'
    + '<div class="xh-banner xh-info">' + ICON.info + '<span class="xh-banner-text">' + (n >= D.cutoffDays
      ? 'You can ask to cancel until <b>' + lastCancelDay({ session_date: S.date }) + '</b>. After that, message ' + esc(first) + ' instead.'
      : 'This session is within ' + D.cutoffDays + ' days, so <b>it can\'t be cancelled here</b> once booked.') + '</span></div></div>'
    + '<div class="xh-dlg-foot"><span class="xh-fnote">' + ICON.clock + 'Booked right away. ' + esc(first) + ' gets an email.</span>'
    + '<button type="button" class="xh-btn xh-ghost" data-xh="close">Cancel</button>'
    + '<button type="button" class="xh-btn xh-gold" data-xh="book-send" id="xh-book-send" disabled>Book session</button></div>',
    'panel',
    function () { if (S.slotId) { S.slotId = null; renderBook(); } });
}

/* The ticked deliverables, with Others as "Others: what they typed". */
function delivPicked() {
  var picked = Array.prototype.map.call(document.querySelectorAll('#xh-deliv-dd [data-deliv]:checked'), function (c) { return c.value; });
  var other = $('xh-deliv-others').checked ? $('xh-deliv-other').value.trim() : '';
  return other ? picked.concat('Others: ' + other) : picked;
}

/* The closed dropdown reads like the mockup: "Chapters 1–3, Logical diagram, Others: Gantt chart". */
function syncBook() {
  var picked = delivPicked();
  var sum = $('xh-deliv-sum');
  sum.textContent = picked.length ? picked.map(function (t) { return t.replace(/ \(.*\)$/, '').replace(/, together$/, ''); }).join(', ') : 'Choose deliverables';
  sum.classList.toggle('xh-mut', !picked.length);
  $('xh-book-send').disabled = !picked.length;
}

function delivError(msg) {
  $('xh-deliv-err').textContent = msg;
  $('xh-f-deliv').classList.add('is-bad');
}

function findBooking(id) {
  return upcoming.concat(recent).filter(function (b) { return Number(b.booking_id) === Number(id); })[0] || null;
}

function openSession(id) {
  var b = findBooking(id);
  if (!b) { return; }
  var isPast = recent.indexOf(b) !== -1;
  var role = roleOfExpert(b.expert_id);
  var first = firstName(b.expert_name);
  var note = '';
  var ask = '';
  if (!isPast && b.cancel_requested) {
    note = '<div class="xh-banner xh-wait">' + ICON.clock + '<span class="xh-banner-text">You asked to cancel this. It stays booked until ' + esc(first) + ' answers, and your whole team is told either way.</span></div>';
  } else if (!isPast && canCancel(b)) {
    note = '<div class="xh-banner">' + ICON.clock + '<span class="xh-banner-text">You can ask to cancel until <b>' + lastCancelDay(b) + '</b>.</span></div>';
    ask = '<div class="xh-dangerrow"><div><b>Can\'t make it?</b><span>Ask ' + esc(first) + ' to cancel.</span></div>'
      + '<button type="button" class="xh-btn xh-danger-ghost xh-sm" data-xh="cancel-open" data-id="' + b.booking_id + '">Ask to cancel</button></div>';
  } else if (!isPast) {
    note = '<div class="xh-banner">' + ICON.lock + '<span class="xh-banner-text">This session is within ' + D.cutoffDays + ' days, so it can\'t be cancelled here. Message ' + esc(first) + ' if you can\'t make it.</span></div>';
  }
  var status = isPast ? '<span class="xh-t-gray">Ended</span>' : b.cancel_requested ? '<span class="xh-t-amber">Cancel asked</span>' : '<span class="xh-t-green">Scheduled</span>';
  openDialog(head(esc(b.expert_name), '', '<span class="xh-dav" aria-hidden="true">' + esc(initialsOf(b.expert_name)) + '</span>', 'Consultation' + (role ? ' · ' + ucfirst(role) : ''))
    + '<div class="xh-dlg-body">'
    + (b.session_date
      ? slotCard(b.session_date, DAYS[parseKey(b.session_date).getDay()] + ' · ' + fmtT(b.start_time) + (b.end_time ? ' – ' + fmtT(b.end_time) : ''), status)
      : '<div class="xh-slotc"><span class="xh-slott"><b>' + esc(b.available_date || '—') + ' · ' + fmtT(b.start_time) + '</b><span>' + status + '</span></span></div>')
    + '<div class="xh-facts">'
    + '<div class="xh-fact"><span>About</span><b>' + esc(b.topics || '—') + '</b></div>'
    + '<div class="xh-fact"><span>Deliverables</span><b>' + esc(b.deliverables || '—') + '</b></div>'
    + '<div class="xh-fact"><span>Your note</span><b>' + esc(b.note || 'No note') + '</b></div>'
    + '<div class="xh-fact"><span>Booked by</span><b>' + esc(b.booked_by || '—') + (b.booked_on ? ' · ' + esc(b.booked_on) : '') + '</b></div></div>'
    + note + ask + '</div>');
}

function openCancel(id) {
  var b = findBooking(id);
  if (!b) { return; }
  var first = firstName(b.expert_name);
  openDialog(head('Ask to cancel this session?', '', '<span class="xh-dlg-icon xh-ic-red">' + ICON.cal + '</span>')
    + '<span class="xh-chipline"><i aria-hidden="true">' + esc(initialsOf(b.expert_name)) + '</i>' + esc(first)
    + (b.session_date ? ' · ' + shortDate(b.session_date) : '') + ' · ' + fmtT(b.start_time) + '</span>'
    + '<p class="xh-ask-text">It stays booked until ' + esc(first) + ' answers. Your whole team is told either way.</p>'
    + '<div class="xh-dlg-body"><div class="xh-field" id="xh-f-cancel"><label for="xh-cancel-reason">Why do you need to cancel? <span class="xh-req">*</span></label>'
    + '<textarea id="xh-cancel-reason" rows="3" maxlength="1000" placeholder="For example: our draft is not ready for review yet."></textarea>'
    + '<span class="xh-fmeta"><span class="xh-ferr">Enter a reason for ' + esc(first) + '.</span></span></div></div>'
    + '<div class="xh-dlg-foot is-plain"><button type="button" class="xh-btn xh-ghost" data-xh="cancel-back" data-id="' + b.booking_id + '">Back</button>'
    + '<button type="button" class="xh-btn xh-danger" data-xh="cancel-send" data-id="' + b.booking_id + '">Send request</button></div>', 'ask');
}

/* ---------------- data refresh ---------------- */
var refreshing = false;
function refresh(passive) {
  if (refreshing) { return Promise.resolve(); }
  refreshing = true;
  var headers = { 'Accept': 'application/json' };
  if (passive) { headers['X-Idle-Passive'] = '1'; }
  return fetch(D.urls.refresh, { headers: headers })
    .then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
    .then(function (data) {
      slots = data.availableSlots || [];
      full = new Set(data.fullOccurrences || []);
      windows = data.bookingWindows || {};
      bookedExpertIds = (data.bookedExpertIds || []).map(Number);
      upcoming = data.upcomingBookings || [];
      recent = data.recentBookings || [];
      render();
    })
    .catch(function () { /* the next poll tries again */ })
    .then(function () { refreshing = false; });
}

/* ---------------- actions ---------------- */
var ACT = {
  close: function (el) { closeDialog(el.closest('dialog')); },
  tab: function (el) {
    // Find experts always opens on the role the server listed: the first one
    // the team has not filled, unless the address asked for another.
    var tab = el.dataset.tab;
    S.tab = tab;
    try {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', tab);
      history.replaceState(null, '', url.pathname + url.search);
    } catch (e) { /* ignore */ }
    render();
    if (tab === 'find') { FIND.rows = 2; findPage(); }
    // A tab opened for the first time since signing in comes up as its
    // skeleton (student/partials/content-skeleton), like a page.
    if (window.contentSkeleton) { window.contentSkeleton.show(tab); }
    $(tab === 'find' ? 'xh-tab-find' : 'xh-tab-book').focus();
  },
  // The calendar and session list open over the page, for one expert's role.
  schedule: function (el) {
    if (el.dataset.role) { S.role = el.dataset.role; }
    S.date = null;
    S.slotId = null;
    S.monthOffset = 0;
    S.sched = true;
    var split = document.querySelector('#xh-book-holder .xh-split');
    var d = openDialog(head('Schedule a consultation', 'Pick a date, then a time.', '', ''), 'wide', function () {
      $('xh-book-holder').appendChild(split);
      S.sched = false;
    });
    d.querySelector('.cm-box').appendChild(split);
    renderBook();
    renderSessions();
  },
  slot: function (el, e) {
    // A time opens its booking straight away; there is no second button to find.
    if (el.classList.contains('xh-tslot')) {
      S.slotId = Number(el.dataset.id);
      renderBook();
      openBookConfirm();
      return;
    }
    // On the booking tab a card picks who to book, without leaving the page.
    if (S.tab === 'consultations') {
      e.preventDefault();
      S.role = el.dataset.role;
      S.date = null;
      S.slotId = null;
      S.monthOffset = 0;
      render();
    }
  },
  month: function (el) { S.monthOffset = Math.max(0, Math.min(MONTHS_AHEAD, S.monthOffset + Number(el.dataset.dir))); S.date = null; S.slotId = null; renderBook(); },
  date: function (el) { S.date = el.dataset.key; S.slotId = null; renderBook(); },
  jump: function (el) {
    S.monthOffset = Math.max(0, Math.min(MONTHS_AHEAD, monthOffsetOf(el.dataset.key)));
    S.date = el.dataset.key;
    S.slotId = null;
    renderBook();
  },
  list: function (el) { S.listTab = el.dataset.list; S.showAll = false; renderSessions(); },
  'show-all': function () { S.showAll = !S.showAll; renderSessions(); },
  // Meet first: pick one of the expert's open times, then book it.
  'meet-pick': function (el) {
    $('xh-meet').querySelectorAll('[data-xh="meet-pick"]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === el)); });
    $('xh-meet').querySelector('[data-xh="meet-book"]').disabled = false;
  },
  'meet-book': function (el) {
    var pick = $('xh-meet').querySelector('[data-xh="meet-pick"][aria-pressed="true"]');
    if (!pick) { return; }
    busy(el, 'Booking…');
    send(D.urls.book, 'POST', { schedule_id: Number(pick.dataset.schedule), session_date: pick.dataset.date }).then(function (data) {
      if (!data.success) { idle(el); toast(data.message || 'That time could not be booked. Pick another.', 'error'); return; }
      toast('Booked. It shows in both schedules.', 'success');
      setTimeout(function () { window.location.reload(); }, 900);
    }).catch(function () { idle(el); toast('Network error. Please try again.', 'error'); });
  },
  'book-send': function (el) {
    var ex = expertFor(S.role);
    var deliverables = delivPicked();
    if (!deliverables.length) { delivError('Pick at least one.'); return; }
    var s = slots.filter(function (x) { return x.schedule_id === S.slotId; })[0];
    var when = shortDate(S.date) + (s ? ' · ' + fmtT(s.start_time) : '');
    busy(el, 'Booking…');
    send(D.urls.book, 'POST', { schedule_id: S.slotId, session_date: S.date, deliverables: deliverables }).then(function (data) {
      if (data.success) {
        S.date = null; S.slotId = null; S.listTab = 'upcoming';
        ACT.close(el);
        toast('Booked ' + when + ' with ' + (ex ? ex.name : 'your ' + S.role) + '.', 'success');
      } else {
        S.slotId = null;
        ACT.close(el);
        toast(data.message || 'That time could not be booked. Pick another.', 'error');
      }
      refresh(false);
    }).catch(function () { idle(el); toast('Network error. Please try again.', 'error'); });
  },
  'ta-pick': function (el) { taPick(el.dataset.id); },
  'ta-clear': function () { taPick(null); },
  session: function (el) { openSession(el.dataset.id); },
  'cancel-open': function (el) { closeDialog(el.closest('dialog')); openCancel(el.dataset.id); },
  'cancel-back': function (el) { closeDialog(el.closest('dialog')); openSession(el.dataset.id); },
  'cancel-send': function (el) {
    var reason = $('xh-cancel-reason').value.trim();
    if (!reason) { $('xh-f-cancel').classList.add('is-bad'); $('xh-cancel-reason').focus(); return; }
    var b = findBooking(el.dataset.id);
    busy(el, 'Sending…');
    send(D.urls.cancel + '/' + el.dataset.id, 'POST', { reason: reason }).then(function (data) {
      if (data.success) {
        closeAll();
        toast('Cancellation sent. ' + (b ? b.expert_name : 'Your expert') + ' will approve or decline it.', 'success');
        refresh(false);
      } else {
        idle(el);
        toast(data.message || 'Could not ask to cancel this booking.', 'error');
      }
    }).catch(function () { idle(el); toast('Network error. Please try again.', 'error'); });
  },
  // Swapping between a profile and its comments replaces the window rather than stacking one on another.
  profile: function (el) { closeDialog(el.closest('dialog')); openProfile(el.dataset.id); },
  apply: function (el) {
    closeDialog(el.closest('dialog'));
    S.draft = null;
    openApply(el.dataset.id, null);
  },
  'apply-review': function (el) {
    var dlg = el.closest('dialog');
    var body = dlg.querySelector('[data-expert]');
    var picked = dlg.querySelector('input[name="xh-apply-role"]:checked');
    var msg = $('xh-apply-msg').value.trim();
    var ok = true;
    $('xh-f-role').classList.toggle('is-bad', !picked);
    if (!picked) { ok = false; }
    var n = words(msg);
    if (n === 0 || n > 500) {
      $('xh-msg-err').textContent = n === 0 ? 'Write a short message to ' + EXPERTS[$('xh-apply-msg').closest('[data-expert]').dataset.expert].name + '.' : 'Keep it to 500 words or fewer.';
      $('xh-f-msg').classList.add('is-bad');
      ok = false;
    }
    if (!ok) { return; }
    S.draft = { expertId: body.dataset.expert, roleId: picked.value, roleName: picked.dataset.name, message: msg };
    closeDialog(dlg);
    openReview(S.draft);
  },
  'apply-edit': function (el) { ACT.close(el); openApply(S.draft.expertId, S.draft); },
  'apply-send': function (el) {
    var d = S.draft;
    var form = new FormData();
    form.append('expert_id', d.expertId);
    form.append('expert_role_id', d.roleId);
    form.append('message', d.message);
    busy(el, 'Sending…');
    send(D.urls.apply, 'POST', form, true).then(function (data) {
      if (data.success) {
        closeAll();
        toast(data.message || 'Application sent.', 'success');
        reloadOnFind();
      } else {
        idle(el);
        toast(data.message || 'The application could not be sent.', 'error');
      }
    }).catch(function () { idle(el); toast('Network error. Please try again.', 'error'); });
  },
  proposal: function () { openProposal(); },
  pdf: function () { if (window.previewPdfUrl && D.proposalPdfUrl) { window.previewPdfUrl(D.proposalPdfUrl, D.proposalTitle || 'Pre-proposal'); } },
  change: function (el) { openChange(el); },
  'change-send': function (el) {
    var reason = $('xh-change-reason').value.trim();
    if (reason.length < D.reasonMin) { $('xh-f-reason').classList.add('is-bad'); return; }
    if (!changeReady()) { return; }
    // A form, not JSON: the replacement may need a PDF. The verb rides in _method.
    var body = new FormData();
    body.append('_method', 'DELETE');
    body.append('reason', reason);
    body.append('replacement_expert_id', $('xh-change-to').value);
    body.append('replacement_message', $('xh-change-msg').value.trim());
    if ($('xh-change-file') && $('xh-change-file').files[0]) { body.append('replacement_proposal_file', $('xh-change-file').files[0]); }
    busy(el, 'Sending…');
    send(D.urls.assignments + '/' + el.dataset.assignment, 'POST', body, true).then(function (data) {
      if (data.success) { closeAll(); toast(data.message, 'success'); reloadOnFind(); }
      else { idle(el); toast(data.message || 'The request could not be sent.', 'error'); }
    }).catch(function () { idle(el); toast('Network error. Please try again.', 'error'); });
  },
  takeback: function (el) { openTakeBack(el); },
  'takeback-send': function (el) {
    busy(el, 'Taking back…');
    send(D.urls.removals + '/' + el.dataset.request, 'DELETE').then(function (data) {
      if (data.success) { closeAll(); toast(data.message, 'success'); reloadOnFind(); }
      else { idle(el); toast(data.message || 'The request could not be taken back.', 'error'); }
    }).catch(function () { idle(el); toast('Network error. Please try again.', 'error'); });
  },
  app: function (el) { openApp(el.dataset.id); },
  'app-withdraw': function (el) { openWithdraw(el.dataset.id); },
  'app-withdraw-send': function (el) {
    var a = APPS[el.dataset.id];
    busy(el, 'Removing…');
    send(D.urls.assignments + '/' + a.id, 'DELETE', { reason: a.status === 'pending' ? 'Withdrawn by the team leader before the expert answered.' : 'Cleared from the list after the expert declined.' }).then(function (data) {
      if (data.success) { closeAll(); toast(a.status === 'pending' ? 'Application to ' + a.name + ' withdrawn.' : 'Removed from your list.', 'success'); reloadOnFind(); }
      else { idle(el); toast(data.message || 'That could not be removed.', 'error'); }
    }).catch(function () { idle(el); toast('Network error. Please try again.', 'error'); });
  },
  'skill-toggle': function (el) {
    var pop = $('xh-skill-pop');
    pop.hidden = !pop.hidden;
    el.setAttribute('aria-expanded', String(!pop.hidden));
    if (!pop.hidden) { $('xh-skill-q').focus(); }
  },
  side: function (el) { FIND.side = el.dataset.side; FIND.page = 0; findPage(); },
  page: function (el) { FIND.page += Number(el.dataset.dir); findPage(); }
};

/* Find experts shows one side - open slots, or full and not taking - a page
   of cards at a time: up to three across, two rows when both fit on the
   screen and one when they do not. A phone scrolls, so it gets six. */
var FIND = { side: 'open', page: 0, rows: 2 };
function findPage() {
  var box = $('xh-results');
  if (!box || S.tab !== 'find') { return; }
  var cards = Array.prototype.slice.call(box.querySelectorAll('.xh-xcard'));
  var side = cards.filter(function (c) { return c.dataset.side === FIND.side; });
  var phone = window.innerWidth <= 900;
  var cols = Math.min(3, Math.max(1, Math.floor((box.clientWidth + 12) / 282)));
  var per = phone ? 6 : cols * FIND.rows;
  var pages = Math.max(1, Math.ceil(side.length / per));
  FIND.page = Math.max(0, Math.min(FIND.page, pages - 1));
  var from = FIND.page * per;
  cards.forEach(function (c) { var i = side.indexOf(c); c.hidden = i < from || i >= from + per; });
  box.querySelectorAll('[data-xh="side"]').forEach(function (b) { b.setAttribute('aria-pressed', String(b.dataset.side === FIND.side)); });
  $('xh-side-empty').hidden = side.length > 0;
  $('xh-pgnav').hidden = side.length === 0;
  $('xh-pg-range').innerHTML = 'Page <b>' + (FIND.page + 1) + '</b> of ' + pages;
  $('xh-pg-dots').innerHTML = pages > 1 && pages <= 8
    ? Array.from({ length: pages }, function (_, i) { return '<i' + (i === FIND.page ? ' class="on"' : '') + '></i>'; }).join('') : '';
  box.querySelector('.xh-keys').hidden = pages < 2;
  box.querySelector('.xh-pg[data-dir="-1"]').disabled = FIND.page === 0;
  box.querySelector('.xh-pg[data-dir="1"]').disabled = FIND.page >= pages - 1;
  if (!phone && FIND.rows > 1 && side.length > cols && box.getBoundingClientRect().bottom > window.innerHeight) { FIND.rows = 1; findPage(); }
}
// The open side is empty but the other is not: start on the other.
if ($('xh-results') && !document.querySelector('#xh-results [data-side="open"]')) { FIND.side = 'closed'; }
findPage();
var findResize;
window.addEventListener('resize', function () { clearTimeout(findResize); findResize = setTimeout(function () { FIND.rows = 2; findPage(); }, 120); });

document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-xh]');
  var pop = $('xh-skill-pop');
  if (pop && !pop.hidden && !e.target.closest('#xh-skill')) { pop.hidden = true; $('xh-skill-trigger').setAttribute('aria-expanded', 'false'); }
  var dd = $('xh-deliv-dd');
  if (dd && dd.open && !e.target.closest('#xh-deliv-dd')) { dd.open = false; }
  if (!el || el.disabled) { return; }
  var fn = ACT[el.dataset.xh];
  if (fn) { fn(el, e); }
});

document.addEventListener('input', function (e) {
  var t = e.target;
  if (t.id === 'xh-change-q') {
    taSearch(t.value.trim());
  } else if (t.id === 'xh-deliv-other') {
    $('xh-f-deliv').classList.remove('is-bad');
    syncBook();
  } else if (t.id === 'xh-apply-msg') {
    var n = words(t.value);
    $('xh-msg-count').textContent = n;
    if (n > 0 && n <= 500) { $('xh-f-msg').classList.remove('is-bad'); }
  } else if (t.id === 'xh-change-reason') {
    var len = t.value.trim().length;
    $('xh-reason-count').textContent = t.value.length;
    $('xh-change-send').disabled = !changeReady();
    $('xh-f-reason').classList.toggle('is-bad', len > 0 && len < D.reasonMin);
  } else if (t.id === 'xh-change-msg') {
    $('xh-change-send').disabled = !changeReady();
  } else if (t.id === 'xh-cancel-reason') {
    if (t.value.trim()) { $('xh-f-cancel').classList.remove('is-bad'); }
  } else if (t.id === 'xh-skill-q') {
    // Narrows the picker itself; the list of experts only changes when a skill is chosen.
    var q = t.value.trim().toLowerCase();
    var any = false;
    document.querySelectorAll('#xh-skill-list .xh-skill-group').forEach(function (g) {
      var groupHit = q !== '' && (g.dataset.name || '').indexOf(q) !== -1;
      var shown = 0;
      g.querySelectorAll('.xh-skill-opt').forEach(function (o) {
        var isAny = o.classList.contains('xh-skill-any');
        var hit = q === '' || groupHit || (!isAny && (o.dataset.name || '').indexOf(q) !== -1);
        o.hidden = !hit;
        if (hit && !isAny) { shown++; }
      });
      var visible = q === '' || shown > 0 || groupHit;
      g.hidden = !visible;
      if (q !== '') { g.open = visible; }
      if (visible) { any = true; }
    });
    var all = document.querySelector('#xh-skill-list [data-all]');
    if (all) { all.hidden = q !== ''; }
    $('xh-skill-nomatch').hidden = any || q === '';
  }
});
document.addEventListener('change', function (e) {
  if (e.target.classList && e.target.classList.contains('xh-file-input')) { syncFilePicker(e.target); }
  if (e.target.id === 'xh-change-to' || e.target.id === 'xh-change-file') { $('xh-change-send').disabled = !changeReady(); }
  if (e.target.closest && e.target.closest('#xh-deliv-dd')) {
    // At most 10 reach the server; Others counts as one.
    if (e.target.checked && document.querySelectorAll('#xh-deliv-dd input:checked').length > 10) { e.target.checked = false; delivError('Pick at most 10.'); return; }
    if (e.target.id === 'xh-deliv-others') { $('xh-deliv-other').hidden = !e.target.checked; if (e.target.checked) { $('xh-deliv-other').focus(); } }
    $('xh-f-deliv').classList.remove('is-bad');
    syncBook();
  }
  if (e.target.name === 'xh-apply-role') { $('xh-f-role').classList.remove('is-bad'); $('xh-msg-to').textContent = 'Message to your ' + e.target.dataset.name; }
});
document.querySelector('.xh-seg').addEventListener('keydown', function (e) {
  if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
    e.preventDefault();
    ACT.tab({ dataset: { tab: S.tab === 'find' ? 'consultations' : 'find' } });
  }
});
// Each expert card says how far it is, and the sheet counts the answers.
function evalTally(form) {
  var done = 0, picked = 0, total = 0;
  form.querySelectorAll('.xh-ex').forEach(function (card) {
    var rows = card.querySelectorAll('.xh-ev-q');
    var n = 0;
    rows.forEach(function (q) {
      if (q.querySelector('input:checked')) { q.classList.remove('is-missing'); n++; }
    });
    var status = card.querySelector('[data-status]');
    var left = rows.length - n;
    status.textContent = !left ? 'Done' : n ? left + ' left' : 'Not started';
    status.className = 'xh-ex-status' + (!left ? ' is-done' : n ? ' is-part' : '');
    if (!left) { done++; }
    picked += n; total += rows.length;
  });
  var d = form.querySelector('[data-done]');
  if (d) { d.textContent = done; }
  var left = form.querySelector('[data-left]');
  if (left) {
    left.innerHTML = '<b>' + picked + '</b> of ' + total + ' questions answered';
    left.classList.toggle('is-ready', picked === total);
    left.classList.remove('is-bad');
  }
}
document.addEventListener('change', function (e) {
  var form = e.target.closest && e.target.closest('.xh-ev-form');
  if (form && /^evaluations\[/.test(e.target.name || '')) { evalTally(form); }
});
document.querySelectorAll('.xh-ev-form').forEach(evalTally);
// One expert open at a time; "Next" moves on to the following card.
document.addEventListener('toggle', function (e) {
  var card = e.target;
  if (!card.classList || !card.classList.contains('xh-ex') || !card.open) { return; }
  card.closest('.xh-ev-form').querySelectorAll('.xh-ex[open]').forEach(function (c) { if (c !== card) { c.open = false; } });
}, true);
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-next]');
  if (!b) { return; }
  var next = b.closest('.xh-ex').nextElementSibling;
  if (next && next.classList.contains('xh-ex')) {
    next.open = true;
    next.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
});

// Sending: every question answered first - the first expert with gaps opens
// and its rows are marked - then one look at every rating before it goes,
// since none can be changed afterwards.
document.addEventListener('submit', function (e) {
  var form = e.target.closest('.xh-ev-form');
  if (!form || form.dataset.confirmed) { return; }
  e.preventDefault();
  var missing = Array.prototype.filter.call(form.querySelectorAll('.xh-ev-q'), function (q) { return !q.querySelector('input:checked'); });
  form.querySelectorAll('.xh-ev-q').forEach(function (q) { q.classList.toggle('is-missing', missing.indexOf(q) !== -1); });
  if (missing.length) {
    missing[0].closest('.xh-ex').open = true;
    missing[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
    var first = missing[0].querySelector('input');
    if (first) { first.focus({ preventScroll: true }); }
    var left = form.querySelector('[data-left]');
    if (left) { left.textContent = 'Answer ' + missing.length + ' more ' + (missing.length === 1 ? 'question' : 'questions') + ' to send'; left.classList.add('is-bad'); }
    return;
  }
  var rows = Array.prototype.map.call(form.querySelectorAll('.xh-ex'), function (card) {
    var picks = Array.prototype.map.call(card.querySelectorAll('input:checked'), function (i) { return Number(i.value); });
    var avg = Math.round(picks.reduce(function (a, b) { return a + b; }, 0) / picks.length * 10) / 10;
    return '<div class="xh-ev-sum"><div class="xh-ev-sum-who"><b>' + esc(card.dataset.expert) + '</b><span>' + esc(card.dataset.role) + '</span></div>'
      + '<div class="xh-ev-sum-score"><b>' + avg + '</b><small>/ 5</small></div></div>';
  });
  var n = rows.length;
  var d = openDialog(head(n === 1 ? 'Send this evaluation?' : 'Send all ' + n + ' evaluations?', esc(form.dataset.stage))
    + '<div class="xh-dlg-body"><div class="xh-ev-sums">' + rows.join('') + '</div>'
    + '<p class="xh-note">Once sent they cannot be changed. Your CaPSA and the experts see the answers, never that they came from you.</p></div>'
    + '<div class="xh-dlg-foot"><button type="button" class="xh-btn xh-ghost" data-xh="close">Review answers</button><button type="button" class="xh-btn xh-gold" data-ev-send>' + (n === 1 ? 'Send evaluation' : 'Send all ' + n) + '</button></div>', 'ask');
  d.querySelector('[data-ev-send]').addEventListener('click', function () {
    busy(this, 'Sending…');
    var btn = form.querySelector('[type="submit"]');
    if (btn) { busy(btn, 'Sending…'); }
    form.dataset.confirmed = '1';
    form.submit();
  });
  d.querySelector('[data-ev-send]').focus();
});
document.addEventListener('keydown', function (e) {
  // The change type-ahead: arrows move through the matches, Enter picks one.
  if (e.target.id === 'xh-change-q' && !$('xh-ta-list').hidden) {
    var opts = Array.prototype.slice.call($('xh-ta-list').querySelectorAll('.xh-ta-opt'));
    var at = opts.map(function (o) { return o.getAttribute('aria-selected'); }).indexOf('true');
    if (opts.length && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
      e.preventDefault();
      at = (at + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % opts.length;
      opts.forEach(function (o, i) { o.setAttribute('aria-selected', String(i === at)); });
      return;
    }
    if (e.key === 'Enter' && opts[at]) { e.preventDefault(); taPick(opts[at].dataset.id); return; }
  }
  if (e.key === 'Escape') { var pop = $('xh-skill-pop'); if (pop) { pop.hidden = true; } return; }
  // Left and right flip Find experts' pages, unless typing, a modifier is
  // held, the skill list or a dialog is open, or the tabs took the key.
  if ((e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') || e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) { return; }
  if (S.tab !== 'find' || e.target.closest('input, textarea, select, [contenteditable]') || document.querySelector('dialog[open]')) { return; }
  var skillPop = $('xh-skill-pop');
  if (skillPop && !skillPop.hidden) { return; }
  var btn = document.querySelector('#xh-results .xh-pg[data-dir="' + (e.key === 'ArrowLeft' ? -1 : 1) + '"]');
  if (btn && !btn.disabled) { e.preventDefault(); btn.click(); }
});

// Kept current without a reload: a slot the expert adds, or a booking a
// teammate makes or cancels. Paused while the tab is hidden.
setInterval(function () { if (document.visibilityState === 'visible') { refresh(true); } }, 30000);
document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') { refresh(true); } });

// Arriving from the duplication report's "Apply" link: open that expert's
// application, or say why it cannot be opened. One-shot, so a reload does
// not open it again.
(function () {
  var wanted = new URLSearchParams(window.location.search).get('apply');
  if (!wanted) { return; }
  var cleaned = new URL(window.location.href);
  cleaned.searchParams.delete('apply');
  history.replaceState(null, '', cleaned.pathname + cleaned.search + cleaned.hash);
  var card = document.querySelector('.xh-xcard[data-expert-id="' + CSS.escape(wanted) + '"]');
  if (!card) { toast('That expert is not in the current list. Clear any filters to find them.', 'error'); return; }
  card.scrollIntoView({ behavior: 'smooth', block: 'center' });
  if (EXPERTS[wanted] && EXPERTS[wanted].apply) {
    setTimeout(function () { openApply(wanted, null); }, 400);
  } else {
    var why = EXPERTS[wanted] && EXPERTS[wanted].status ? EXPERTS[wanted].status.text : 'You cannot apply to this expert right now.';
    toast('You cannot apply to this expert right now: ' + why, 'error');
  }
})();

render();
if (window.contentSkeleton) { window.contentSkeleton.seen(S.tab); }
})();

// -- Mobile sidebar --
function toggleSidebar() {
  document.body.classList.toggle('sidebar-open');
  document.body.style.overflow = document.body.classList.contains('sidebar-open') ? 'hidden' : '';
}
function closeSidebar() {
  document.body.classList.remove('sidebar-open');
  document.body.style.overflow = '';
}
document.querySelectorAll('.nav-item').forEach(function (el) {
  el.addEventListener('click', function () { if (window.innerWidth <= 768) { closeSidebar(); } });
});
</script>
@include('partials.escape-closes-modals')
@include('partials.app-scrollbar')
</body>
</html>
