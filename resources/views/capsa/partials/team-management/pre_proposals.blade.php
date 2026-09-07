{{-- Pre-Proposals: the review queue. Teams with titles still awaiting a
     decision come first, since they are what this tab is asking of her;
     teams whose titles are all settled stay listed so she can look back. --}}
@php
  $waiting = $rows->filter(fn ($r) => $r->pendingProposalCount > 0)->values();
  $settled = $rows->filter(fn ($r) => $r->pendingProposalCount === 0 && $r->proposals->isNotEmpty())->values();
@endphp

<p class="tm-note">
  Approving a title is what sets a team's project title. Approve one and it is theirs;
  approve two and the team chooses between them on their Duplication Checker page.
</p>

<div class="card" style="padding:0;overflow:hidden">
  <div class="tbl-scroll"><table class="table">
    <thead>
      <tr>
        <th style="padding-left:20px">Team</th>
        <th>Section</th>
        <th>Titles submitted</th>
        <th>Awaiting review</th>
        <th>Outcome</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($waiting->concat($settled) as $row)
        <tr>
          <td style="padding-left:20px">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,{{ $row->gradient }});display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">{{ $row->initials }}</div>
              <span class="td-main">{{ $row->team->team_name }}</span>
            </div>
          </td>
          <td><span class="badge {{ $row->section === \App\Support\SectionScope::UNASSIGNED ? 'badge-gray' : 'badge-gold' }}" style="font-size:10px">{{ $row->section }}</span></td>
          {{-- The number of titles a team is asked for lives on the controller that
     enforces it. Spelled out here as 3, this went on reading "4 of 3" from
     the day that number became four. --}}
<td style="font-size:12px;color:var(--text2)">{{ $row->proposals->count() }} of {{ \App\Http\Controllers\PreProposalController::RECOMMENDED_PROPOSALS }}</td>
          <td>
            @if ($row->pendingProposalCount > 0)
              <span class="tm-pill tm-pill-amber">{{ $row->pendingProposalCount }} to review</span>
            @else
              <span style="font-size:12px;color:var(--text3)">—</span>
            @endif
          </td>
          <td>
            @if ($row->titleState === 'awaiting_team_choice')
              <span class="tm-pill tm-pill-amber">Team choosing ({{ $row->approvedProposalCount }})</span>
            @elseif ($row->team->project_title)
              <div style="font-size:12px;color:var(--text2);white-space:normal;word-break:break-word;line-height:1.45">{{ $row->team->project_title }}</div>
            @else
              <span class="tm-pill tm-pill-grey">Not settled</span>
            @endif
          </td>
          <td>
            <button type="button" class="btn {{ $row->pendingProposalCount > 0 ? 'btn-primary' : '' }}"
              style="font-size:10px;padding:4px 10px;{{ $row->pendingProposalCount > 0 ? '' : 'background:transparent;color:var(--text2);border:1px solid var(--border)' }}"
              onclick='openVerifyModal({{ json_encode($row->reviewPayload, JSON_HEX_APOS | JSON_HEX_QUOT) }})'>
              {{ $row->pendingProposalCount > 0 ? 'Review titles' : 'View' }}
            </button>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="tm-empty">
            No pre-proposals submitted yet.<br>
            They appear here once a team runs a duplication check and saves a title.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table></div>
</div>
