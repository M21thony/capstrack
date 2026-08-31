{{-- Paper Format Check: papers students sent to their Subject Adviser, which
     is her CaPSA hat. This is the layout pass - margins, spacing, how the
     paper looks - not a read of what it argues.

     Her reviews as an adviser or consultant are a different job and stay in
     My Expert Duties -> Document Review. Only the checking UI is shared:
     opening one here goes to the same annotator. --}}
@php
  $papers = $rows->flatMap(fn ($r) => $r->papers->map(fn ($p) => (object) [
      'paper' => $p,
      'row' => $r,
  ]));
  $toCheck = $papers->filter(fn ($p) => $p->paper->review_status !== 'Reviewed')->values();
  $done = $papers->filter(fn ($p) => $p->paper->review_status === 'Reviewed')->values();
@endphp

<p class="tm-note">
  Papers your students sent you as their <strong>Subject Adviser</strong>. Opening one takes you to the
  annotator &mdash; the same tool as Document Review, which keeps your adviser and consultant work separate from this.
</p>

<div class="card" style="padding:0;overflow:hidden">
  <div class="tbl-scroll"><table class="table">
    <thead>
      <tr>
        <th style="padding-left:20px">Document</th>
        <th>Team</th>
        <th>Section</th>
        <th>Submitted</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @forelse ($toCheck->concat($done) as $entry)
        @php
          $p = $entry->paper;
          $reviewed = $p->review_status === 'Reviewed';
        @endphp
        <tr>
          <td style="padding-left:20px">
            <div class="td-main">{{ $p->file_name }}</div>
            <div style="font-size:11px;color:var(--text3)">{{ $p->chapter ?: 'Chapter not set' }} &middot; v{{ $p->file_version }}</div>
          </td>
          <td style="font-size:12px;color:var(--text2)">{{ $entry->row->team->team_name }}</td>
          <td><span class="badge {{ $entry->row->section === \App\Support\SectionScope::UNASSIGNED ? 'badge-gray' : 'badge-gold' }}" style="font-size:10px">{{ $entry->row->section }}</span></td>
          <td style="font-size:12px;color:var(--text3)">
            {{ $p->submitted_at ? \Illuminate\Support\Carbon::parse($p->submitted_at)->format('M d, Y') : '—' }}
          </td>
          <td>
            @if ($reviewed)
              <span class="tm-pill tm-pill-green">Checked</span>
            @elseif ($p->review_status === 'Reviewing')
              <span class="tm-pill tm-pill-amber">In progress</span>
            @else
              <span class="tm-pill tm-pill-amber">To check</span>
            @endif
          </td>
          <td>
            <a href="{{ route('instructor.docreview.annotate', $p->review_id) }}"
               class="btn {{ $reviewed ? '' : 'btn-primary' }}"
               style="font-size:10px;padding:4px 10px;text-decoration:none;{{ $reviewed ? 'background:transparent;color:var(--text2);border:1px solid var(--border)' : '' }}">
              {{ $reviewed ? 'Open' : 'Check format' }}
            </a>
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="tm-empty">
            Nothing to check right now.<br>
            Papers appear here when a team sends one to their Subject Adviser.
          </td>
        </tr>
      @endforelse
    </tbody>
  </table></div>
</div>
