@if (auth('expert')->user()?->is_staff)
  {{-- An Admin or CAPSA who is also an Adviser/Consultant/Grammarian gets
       the same merged sidebar as the admin portal (admin nav + a "My Expert
       Duties" section) instead of a separate expert-only one - see
       admin/_sidebar for where that section is built. --}}
  @include('capsa._sidebar')
@else
<div class="sidebar">
  @if(!isset($showBrand) || $showBrand)
    <div class="sidebar-brand">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
      </div>
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
@include('partials.mobile-tabbar', ['tabs' => [
    ['label' => 'Home', 'href' => route('instructor.dashboard'), 'active' => request()->routeIs('instructor.dashboard'),
        'icon' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>'],
    ['label' => 'Teams', 'href' => route('instructor.teams'), 'active' => request()->routeIs('instructor.teams'),
        'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['label' => 'Reviews', 'href' => route('instructor.docreview'), 'active' => request()->routeIs('instructor.docreview'), 'badge' => $unseenDocReviewCount ?? 0,
        'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M9 13h6M9 17h4"/>'],
    ['label' => 'Grading', 'href' => route('instructor.grading'), 'active' => request()->routeIs('instructor.grading', 'instructor.grading.show'),
        'icon' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
]])
@include('partials.staff-portal-tour', ['tourKey' => 'expert', 'expert' => $expert])
@include('partials.sidebar-scroll-restore')
@endif
