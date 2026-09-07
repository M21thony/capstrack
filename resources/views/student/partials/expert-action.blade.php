{{-- The one place that decides what a team may do about an expert:
     verified first, already approved, already pending, full, apply, or one of
     the reasons you cannot. Included by both the card and the list row so the
     two can never disagree.

     Expects: $expert, $visibleRoles, $_isFull, and the page's $team,
     $isLeader, $teamConfirmed, $assignments. --}}
            @if($team && $isLeader && !$teamConfirmed)
              <button class="btn btn-sm btn-full btn-muted" disabled>
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z"/></svg>
                Verified First
              </button>
            @elseif($team && $isLeader)
              @php
                $teamAssignmentsForExpert = $assignments->where('expert_id', $expert->expert_id);
                $hasApproved = $teamAssignmentsForExpert->where('status', 'approved')->isNotEmpty();
                $hasPending = $teamAssignmentsForExpert->where('status', 'pending')->isNotEmpty();
              @endphp
              @if($hasApproved)
                <button class="btn btn-sm btn-full btn-muted-green" disabled>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Approved
                </button>
              @elseif($hasPending)
                <button class="btn btn-sm btn-full btn-muted-amber" disabled>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  Pending
                </button>
              @elseif(!$expert->hasSetPrograms())
                {{-- They have not said which programmes they take teams from,
                     or how many, so there is no answer yet to whether they
                     have room for this one. The card already says "Capacity
                     not set" above; this stops the click that would have been
                     refused by the server anyway. --}}
                <button class="btn btn-sm btn-full btn-muted" disabled
                        title="This expert has not set which programs they take teams from yet.">
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  Not Accepting Yet
                </button>
              @elseif(!$_isFull)
                @php
                  // Each role the leader can apply for, carrying the pivot key
                  // the application records - so "as our consultant" is stored,
                  // not guessed at later.
                  $_roleChoices = $visibleRoles->map(fn($r) => [
                      'expert_role_id' => $r->pivot->expert_role_id,
                      'name' => $r->role_name,
                      'label' => ucfirst($r->role_name),
                  ])->values();
                @endphp
                <button class="btn btn-sm btn-full btn-solid" data-apply-expert="{{ $expert->expert_id }}" onclick="openApplyModal({{ $expert->expert_id }}, {{ Js::from($expert->name) }}, {{ Js::from($_roleChoices) }})">
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                  Apply Now
                </button>
              @elseif($_isFull)
                <button class="btn btn-sm btn-full btn-muted" disabled>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  Full
                </button>
              @else
                <button class="btn btn-sm btn-full btn-muted" disabled>
                  <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                  Already Applied
                </button>
              @endif
            @elseif(!$team)
              <button class="btn btn-sm btn-full btn-muted" disabled>
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Join a Team First
              </button>
            @elseif(!$isLeader)
              <button class="btn btn-sm btn-full btn-muted" disabled>
                <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2"><path d="M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z"/></svg>
                Leader Only
              </button>
            @endif
