{{--
  Papers this team sent to her: for the format check as Subject Adviser, or
  to review as one of its experts. $papers from TeamRows; $fill as in _feed.
--}}
<ul class="tp-ppl tp-papers{{ ! empty($fill) ? ' tp-fill' : '' }}">
  @foreach ($papers as $paper)
    @php
      $reviewed = $paper->review_status === 'Reviewed';
      $mark = $reviewed ? 'Checked' : ($paper->review_status === 'Reviewing' ? 'In progress' : 'To check');
      $formatCheck = ($paper->role ?? 'subject_adviser') === 'subject_adviser';
      $meta = collect([
          $paper->chapter ?: 'Chapter not set',
          'v'.$paper->file_version,
          $paper->submitted_at ? \Illuminate\Support\Carbon::parse($paper->submitted_at)->format('M j, Y') : null,
      ])->filter()->implode(' · ');
    @endphp
    <li>
      <span class="tp-paper"><b>{{ $paper->file_name }}</b><small>{{ $meta }} · <span class="{{ $reviewed ? 'ok' : 'due' }}">{{ $mark }}</span></small></span>
      <a class="btn {{ $reviewed ? 'btn-o' : 'btn-p' }}" href="{{ route('instructor.docreview.annotate', $paper->review_id) }}">{{ $reviewed ? 'Open' : ($formatCheck ? 'Check format' : 'Review') }}</a>
    </li>
  @endforeach
</ul>
