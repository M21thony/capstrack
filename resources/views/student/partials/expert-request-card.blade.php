{{--
  One application, in the panel down the right of Find Your Team Experts.

  Lifted out of the page so the panel can loop over two lists with it: the
  ones still waiting on a reply, and the ones already answered. They used to
  be one list under a heading that said "Pending Applications", which made a
  rejected application look like something still being decided.
--}}
          @php
            // Through the public disk explicitly: the file was stored with
            // ->store("proposals", "public"), and a bare Storage::url() call
            // resolves against the DEFAULT disk instead - the same fix the
            // expert side of this link already carries.
            $propUrl = $assignment->proposal_file
                ? Storage::disk("public")->url($assignment->proposal_file)
                : null;
            $reqRole = $assignment->engaged_role_name;
          @endphp
          <div class="req-card">
            <div class="avatar" style="width: 36px; height: 36px; font-size: 12px; background: var(--accent); color: white;">
              {{ $assignment->expert?->avatarInitials() ?? 'NA' }}
              @if ($assignment->expert?->hasAvatarImage())
                <img class="avatar-photo" src="{{ $assignment->expert->avatarUrl() }}" alt="" loading="lazy"
                     referrerpolicy="no-referrer" onerror="this.remove()">
              @endif
            </div>
            <div style="flex: 1; min-width: 0;">
              <div style="font-size: 13px; font-weight: 600; color: var(--text);">{{ $assignment->expert?->name ?? 'Unknown Expert' }}</div>
              <div style="font-size: 11px; color: var(--text3); text-transform: uppercase;">{{ $reqRole ? 'Applied as '.$reqRole : ucfirst($assignment->status) }}</div>
            </div>
            <span class="status-badge" style="background: {{ $assignment->status === 'approved' ? 'var(--green-bg)' : ($assignment->status === 'pending' ? 'var(--amber-bg)' : 'var(--red-bg)') }}; color: {{ $assignment->status === 'approved' ? 'var(--green)' : ($assignment->status === 'pending' ? 'var(--amber)' : 'var(--red)') }};">
              {{ ucfirst($assignment->status) }}
            </span>
            <button class="btn btn-sm btn-soft" onclick="openReqInfoModal({{ json_encode([
              'name' => $assignment->expert?->name ?? 'Unknown',
              'role' => $reqRole ? ucfirst($reqRole) : null,
              'status' => ucfirst($assignment->status),
              'message' => $assignment->message ?? 'No message provided.',
              // Why the expert said no. It was written down and sent in a
              // notification, then never shown again - so a team that missed
              // the notification had a rejected application and no idea why.
              'rejection_reason' => $assignment->rejection_reason,
              // An application made with the CaPSA/TSA-approved pre-proposal has
              // no uploaded file, and the details said "None attached" - as
              // though nothing had been sent, when what was sent is the one
              // document the CaPSA/TSA had already passed.
              'pre_proposal_title' => $assignment->preProposal?->title,
              'proposal' => $propUrl,
              'date' => $assignment->created_at ? $assignment->created_at->format('M d, Y g:i A') : 'N/A',
            ]) }})" title="View details">View</button>
            @if($isLeader && $assignment->status !== 'approved')
              <button class="btn btn-sm btn-soft-red" onclick="openRemoveModal({{ $assignment->assignment_id }}, '{{ $assignment->expert?->name ?? '' }}')" title="Remove">
                <svg viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" fill="none" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            @endif
          </div>
