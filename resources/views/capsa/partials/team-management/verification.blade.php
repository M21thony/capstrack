{{-- Verification: teams marked Complete, waiting on her, then those already
     verified. The blocked case is stated rather than left as a dead button -
     a team whose two recommended titles nobody has picked between cannot be
     verified, and that is not the CaPSA's to fix. --}}
@php
  $awaiting = $rows->filter(fn ($r) => $r->awaitingVerification)->values();
  $verified = $rows->filter(fn ($r) => $r->team->is_confirmed)->values();
@endphp

<div class="card" style="padding:0;overflow:hidden;margin-bottom:16px">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border)">
    <div style="font-size:13px;font-weight:700;color:var(--text)">Awaiting verification</div>
    <div style="font-size:11.5px;color:var(--text3);margin-top:2px">Marked <strong>Complete</strong> by their leader. Verifying unlocks panel assignment.</div>
  </div>
  <div class="tbl-scroll"><table class="table">
    <thead>
      <tr>
        <th style="padding-left:20px">Team</th>
        <th>Section</th>
        <th>Title</th>
        <th>Ready?</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($awaiting as $row)
        <tr>
          <td style="padding-left:20px">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,{{ $row->gradient }});display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">{{ $row->initials }}</div>
              <div style="min-width:0">
                <div class="td-main">{{ $row->team->team_name }}</div>
                <div style="font-size:11px;color:var(--text3)">{{ $row->members->count() }}/{{ \App\Models\Team::maxMembers() }} members</div>
              </div>
            </div>
          </td>
          <td><span class="badge {{ $row->section === \App\Support\SectionScope::UNASSIGNED ? 'badge-gray' : 'badge-gold' }}" style="font-size:10px">{{ $row->section }}</span></td>
          <td style="max-width:250px;font-size:12px;color:var(--text2);white-space:normal;word-break:break-word;line-height:1.45">
            {{ $row->team->project_title ?: '—' }}
          </td>
          <td>
            @if ($row->canVerify)
              <span class="tm-pill tm-pill-green">Ready</span>
            @elseif ($row->titleState === 'awaiting_team_choice')
              <span class="tm-pill tm-pill-amber">Team choosing</span>
              <div style="font-size:11px;color:var(--text3);margin-top:3px;max-width:190px;line-height:1.45">
                {{ $row->approvedProposalCount }} titles recommended &mdash; they pick which one stands before you can verify.
              </div>
            @else
              <span class="tm-pill tm-pill-red">No recommended title</span>
              <div style="font-size:11px;color:var(--text3);margin-top:3px;max-width:190px;line-height:1.45">
                Recommend one of their pre-proposals first.
              </div>
            @endif
          </td>
          <td>
            <button type="button" class="btn {{ $row->canVerify ? 'btn-primary' : '' }}"
              style="font-size:10px;padding:4px 10px;{{ $row->canVerify ? '' : 'background:transparent;color:var(--text2);border:1px solid var(--border)' }}"
              onclick='openVerifyModal({{ json_encode($row->reviewPayload, JSON_HEX_APOS | JSON_HEX_QUOT) }})'>
              {{ $row->canVerify ? 'View & Verify' : 'View' }}
            </button>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="tm-empty">
            No teams awaiting verification.<br>
            They appear here once a leader marks their team <strong>Complete</strong>.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table></div>
</div>

<div class="card" style="padding:0;overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border)">
    <div style="font-size:13px;font-weight:700;color:var(--text)">Verified</div>
  </div>
  <div class="tbl-scroll"><table class="table">
    <thead>
      <tr>
        <th style="padding-left:20px">Team</th>
        <th>Section</th>
        <th>Title</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($verified as $row)
        <tr>
          <td style="padding-left:20px">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:28px;height:28px;border-radius:7px;background:linear-gradient(135deg,{{ $row->gradient }});display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">{{ $row->initials }}</div>
              <span class="td-main">{{ $row->team->team_name }}</span>
            </div>
          </td>
          <td><span class="badge {{ $row->section === \App\Support\SectionScope::UNASSIGNED ? 'badge-gray' : 'badge-gold' }}" style="font-size:10px">{{ $row->section }}</span></td>
          <td style="max-width:280px;font-size:12px;color:var(--text2);white-space:normal;word-break:break-word;line-height:1.45">{{ $row->team->project_title ?: '—' }}</td>
          <td>
            <button type="button" class="btn" style="font-size:10px;padding:4px 10px;background:transparent;color:var(--text2);border:1px solid var(--border)"
              onclick='openVerifyModal({{ json_encode($row->reviewPayload, JSON_HEX_APOS | JSON_HEX_QUOT) }})'>View</button>
          </td>
        </tr>
      @empty
        <tr><td colspan="4" class="tm-empty">No verified teams yet.</td></tr>
      @endforelse
    </tbody>
  </table></div>
</div>
