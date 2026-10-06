@if (auth('expert')->user()?->is_staff)
  {{-- An Admin or CAPSA who is also an Adviser/Consultant/Grammarian gets
       the same merged sidebar as the admin portal (admin nav + a "My Expert
       Duties" section) instead of a separate expert-only one - see
       admin/_sidebar for where that section is built. --}}
  @include('capsa._sidebar')
@else
@include('partials.portal-nav-icons')
<div class="sidebar">
  @if(!isset($showBrand) || $showBrand)
    <div class="sidebar-brand">
      <img src="{{ asset('images/brand/capstrack-mark-small.svg') }}" alt="" width="38" height="38" style="width:38px;height:38px;border-radius:10px;flex-shrink:0;display:block">
      <div>
        <div class="brand-name">CapsTRACK</div>
        <div class="brand-sub">Expert Portal</div>
      </div>
    </div>
  @endif

  @php
    $expert = auth('expert')->user();
    $currentRouteName = request()->route()?->getName();

    $pendingAssignmentIds = $expert ? App\Models\TeamExpertAssignment::where('expert_id', $expert->expert_id)->where('status', 'pending')->pluck('assignment_id')->toArray() : [];
    $seenAssignmentIds = session('instructor_seen_assignment_ids', []);
    $unseenAssignmentCount = count(array_diff($pendingAssignmentIds, $seenAssignmentIds));

    if ($currentRouteName === 'instructor.assignments') {
      session()->put('instructor_seen_assignment_ids', array_unique(array_merge($seenAssignmentIds, $pendingAssignmentIds)));
    }

    $pendingDocReviewIds = [];
    if ($expert && Schema::hasTable('capstone_document_review')) {
      $pendingDocReviewIds = DB::table('capstone_document_review')->where('expert_id', $expert->expert_id)->where('review_status', 'pending')->pluck('review_id')->toArray();
    }
    $seenDocReviewIds = session('instructor_seen_doc_review_ids', []);
    $unseenDocReviewCount = count(array_diff($pendingDocReviewIds, $seenDocReviewIds));

    if ($currentRouteName === 'instructor.docreview') {
      session()->put('instructor_seen_doc_review_ids', array_unique(array_merge($seenDocReviewIds, $pendingDocReviewIds)));
    }

    $scheduleIds = $expert ? DB::table('consultation_schedule')->where('expert_id', $expert->expert_id)->where('is_available', 1)->pluck('schedule_id')->toArray() : [];
    $seenScheduleIds = session('instructor_seen_schedule_ids', []);
    $unseenScheduleCount = count(array_diff($scheduleIds, $seenScheduleIds));

    if ($currentRouteName === 'instructor.schedule') {
      session()->put('instructor_seen_schedule_ids', array_unique(array_merge($seenScheduleIds, $scheduleIds)));
    }
  @endphp

  <div class="sidebar-user" id="ep-trigger" onclick="openExpertProfile()">
    <div class="avatar" style="padding:0;overflow:hidden;">
      <img src="{{ $expert?->avatarUrl() ?? asset('images/default-avatar.jpg') }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;" loading="lazy">
    </div>

    <div class="user-info">
      <div class="user-name">{{ $expert?->first_name }} {{ $expert?->last_name }}</div>
      <div class="user-role">{{ ucfirst($expert?->roles->pluck('role_name')->join(', ', ' & ')) ?? 'Expert' }} Active</div>
    </div>
    <div class="status-dot"></div>
    <div class="su-caret">
      <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
    </div>
  </div>

  @include('instructor.partials.sidebar-nav', [
    'currentPage' => $currentPage ?? '',
    'totalTeams' => $totalTeams ?? 0,
    'unseenAssignmentCount' => $unseenAssignmentCount,
    'unseenDocReviewCount' => $unseenDocReviewCount,
    'unseenScheduleCount' => $unseenScheduleCount,
  ])
</div>
@include('partials.mobile-tabbar', ['floating' => true, 'menuAvatar' => $expert ? ['url' => $expert->hasAvatarImage() ? $expert->avatarUrl() : null, 'initials' => $expert->avatarInitials()] : null, 'tabs' => [
    ['label' => 'Home', 'href' => route('instructor.dashboard'), 'active' => request()->routeIs('instructor.dashboard'),
        'icon' => App\Support\PortalIcons::paths('home')],
    ['label' => 'Teams', 'href' => route('instructor.teams'), 'active' => request()->routeIs('instructor.teams', 'instructor.progress', 'instructor.assignments', 'instructor.assignments.*'),
        'icon' => App\Support\PortalIcons::paths('team')],
    ['label' => 'Reviews', 'href' => route('instructor.docreview'), 'active' => request()->routeIs('instructor.docreview'), 'badge' => $unseenDocReviewCount ?? 0,
        'icon' => App\Support\PortalIcons::paths('file-review')],
    ['label' => 'Grading', 'href' => route('instructor.grading'), 'active' => request()->routeIs('instructor.grading', 'instructor.grading.show'),
        'icon' => App\Support\PortalIcons::paths('grading')],
]])
@include('partials.staff-portal-tour', ['tourKey' => 'expert', 'expert' => $expert])
@include('partials.table-export')
@include('partials.sidebar-scroll-restore')
@endif
