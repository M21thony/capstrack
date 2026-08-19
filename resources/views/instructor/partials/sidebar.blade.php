@if (auth('expert')->user()?->is_admin)
  {{-- A CaPSA who is also an Adviser/Consultant/Grammarian gets the same
       merged sidebar as the admin portal (admin nav + a "My Expert Duties"
       section) instead of a separate expert-only one - see admin/_sidebar
       for where that section is built. --}}
  @include('admin._sidebar')
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
@include('partials.idle-timeout')
@endif
