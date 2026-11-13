{{-- The evaluations this team already sent; sits under the expert list, or beside the page when nothing is due. --}}
<aside class="xh-card xh-rail xh-rail-card xh-ev-history" aria-labelledby="xh-ev-history-h">
  <div class="xh-ev-head">
    <h2 id="xh-ev-history-h">Sent evaluations</h2>
    <span>{{ $evaluationHistory->count() }}</span>
  </div>
  @forelse($evaluationHistory as $ev)
    <div class="xh-ev-sent r-{{ $ev->role }}">
      <div class="xh-ev-sent-top">
        <b>{{ $ev->expert?->name ?? 'Former expert' }}</b>
        <span class="xh-ev-score"><span class="xh-stars" role="img" aria-label="{{ $fmtRating($ev->rating) }} out of 5">{!! $starsOf((float) $ev->rating) !!}</span>{{ $fmtRating($ev->rating) }}</span>
      </div>
      <div class="xh-ev-sent-meta">{{ \App\Models\ExpertEvaluation::ROLES[$ev->role] ?? ucfirst($ev->role) }} · {{ $ev->defense?->defense_type ?? 'Earlier phase' }} · {{ $ev->created_at?->format('M j, Y') }}</div>
      @if($ev->answers)
        <details class="xh-ev-answers">
          <summary>Your answers</summary>
          <ul>
            @foreach($ev->answers as $a)
              <li><span>{{ $a['question'] ?? '' }}</span><b>{{ $a['score'] ?? '' }}</b></li>
            @endforeach
          </ul>
        </details>
      @endif
      @if($ev->comment)
        <p>{{ $ev->comment }}</p>
      @endif
    </div>
  @empty
    <p class="xh-ev-none">No evaluations sent yet.</p>
  @endforelse
</aside>
