{{-- All Teams: how every team in the CaPSA's sections is actually doing.
     Deliberately not limited to teams marked Complete - a team that has
     stalled while still forming is exactly what this page exists to show. --}}
<div class="card" style="padding:0;overflow:hidden">
  <div class="tbl-scroll"><table class="table">
    <thead>
      <tr>
        <th style="padding-left:20px">Team</th>
        <th>Section</th>
        <th>Title</th>
        <th>Experts</th>
        <th>Milestones</th>
        <th>Defense Scores</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($rows as $row)
        <tr>
          <td style="padding-left:20px">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,{{ $row->gradient }});display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">{{ $row->initials }}</div>
              <div style="min-width:0">
                <div class="td-main">{{ $row->team->team_name }}</div>
                <div style="font-size:11px;color:var(--text3)">{{ $row->members->count() }}/{{ \App\Models\Team::maxMembers() }} members &middot; {{ $row->leaderName }}</div>
              </div>
            </div>
          </td>

          <td><span class="badge {{ $row->section === \App\Support\SectionScope::UNASSIGNED ? 'badge-gray' : 'badge-gold' }}" style="font-size:10px">{{ $row->section }}</span></td>

          <td style="max-width:230px">
            @switch($row->titleState)
              @case('settled')
                <div style="font-size:12px;color:var(--text2);white-space:normal;word-break:break-word;line-height:1.45">{{ $row->team->project_title }}</div>
                @break
              @case('awaiting_team_choice')
                {{-- The one state that otherwise looks like an unexplained
                     dead end on the Verification tab. --}}
                <span class="tm-pill tm-pill-amber">Team choosing</span>
                <div style="font-size:11px;color:var(--text3);margin-top:3px">{{ $row->approvedProposalCount }} approved &mdash; waiting on them</div>
                @break
              @case('settling')
                <span class="tm-pill tm-pill-green">Approved</span>
                @break
              @case('awaiting_review')
                <span class="tm-pill tm-pill-amber">Awaiting your review</span>
                @break
              @default
                <span class="tm-pill tm-pill-grey">No titles yet</span>
            @endswitch
          </td>

          <td>
            <div class="tm-roles">
              @foreach (['adviser', 'consultant', 'grammarian'] as $role)
                @php $who = $row->engagedRoles->get($role); @endphp
                <span class="tm-role {{ $who ? 'tm-role-on' : 'tm-role-off' }}" @if($who) title="{{ $who }}" @endif>
                  {{ substr($role, 0, 3) }}
                </span>
              @endforeach
            </div>
          </td>

          <td>
            @if ($row->milestones && $row->milestones->total > 0)
              <div style="display:flex;align-items:center;gap:8px">
                <div class="tm-bar"><div class="tm-bar-fill" style="width:{{ $row->milestones->percent }}%;{{ $row->milestones->overdue > 0 ? 'background:var(--red)' : '' }}"></div></div>
                <span class="tm-bar-label">{{ $row->milestones->completed }}/{{ $row->milestones->total }}</span>
              </div>
              @if ($row->milestones->overdue > 0)
                <div style="font-size:11px;color:var(--red);margin-top:3px">{{ $row->milestones->overdue }} overdue</div>
              @endif
            @else
              <span style="font-size:12px;color:var(--text3)">—</span>
            @endif
          </td>

          <td>
            {{-- The panel's average: every panelist's total over the size of
                 the panel, shown only once all of them have graded. --}}
            @forelse ($defenseScores->get($row->team->team_id, collect()) as $score)
              <div class="tm-score">
                <span class="tm-score-stage">{{ $score->label }}@if ($score->attempt > 1) &middot; redefense @endif</span>
                @if ($score->average !== null)
                  <b>{{ \App\Support\DefenseScores::format($score->average) }}</b>
                @else
                  <span class="tm-score-wait">{{ $score->graded }}/{{ $score->panel_size }} graded</span>
                @endif
              </div>
            @empty
              <span style="font-size:12px;color:var(--text3)">—</span>
            @endforelse
          </td>

          <td>
            @if ($row->team->is_confirmed)
              <span class="tm-pill tm-pill-green">Verified</span>
            @elseif ($row->awaitingVerification)
              <span class="tm-pill tm-pill-amber">To verify</span>
            @else
              <span class="tm-pill tm-pill-grey">{{ ucfirst($row->team->team_status) }}</span>
            @endif
            @if ($row->papersToCheck > 0)
              <div style="font-size:11px;color:var(--text3);margin-top:3px">{{ $row->papersToCheck }} paper(s) to check</div>
            @endif
            {{-- Where they are in the capstone, and whether a stage's window
                 has closed on them. Extending that window in Panel Assignment
                 clears this on its own. --}}
            @if ($row->failedStage)
              <div class="tm-pill tm-pill-red" style="margin-top:4px">Missed {{ \Illuminate\Support\Str::before($row->failedStage, ' Defense') }} deadline</div>
            @elseif ($row->currentStage)
              <div style="font-size:11px;color:var(--text3);margin-top:3px">{{ $row->currentStage }}</div>
            @else
              <div class="tm-pill tm-pill-green" style="margin-top:4px">All defenses passed</div>
            @endif
          </td>

          <td>
            <button type="button" class="btn" style="font-size:10px;padding:4px 10px;background:transparent;color:var(--text2);border:1px solid var(--border)"
              onclick='openVerifyModal({{ json_encode($row->reviewPayload, JSON_HEX_APOS | JSON_HEX_QUOT) }})'>
              <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              View
            </button>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="8" class="tm-empty">
            No teams in {{ $selectedSection ? 'section '.$selectedSection : 'your sections' }} yet.<br>
            Teams appear here as soon as students form them.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table></div>
  <div style="padding:14px 20px;border-top:1px solid var(--border)">
    <span style="font-size:12px;color:var(--text3)">{{ $rows->count() }} team(s)</span>
  </div>
</div>
