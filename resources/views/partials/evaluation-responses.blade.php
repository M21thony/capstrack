{{-- Team leaders' answers, one per evaluation, with nobody's name: the
     default avatar and "Anonymous team leader" stand in for whoever sent it.
     No date or defense either: either one could point to the team.
     $responses comes from App\Support\EvaluationResults::responses(). --}}
<ul class="ev-resps">
  @foreach($responses as $r)
    <li class="ev-resp">
      <div class="ev-resp-top">
        @include('partials.anonymous-avatar')
        <div class="ev-resp-who">
          <b>Anonymous team leader</b>
          <span>As {{ $r->role_label }}</span>
        </div>
        <span class="ev-resp-score">@include('partials.evaluation-stars', ['rating' => $r->rating]){{ \App\Support\EvaluationResults::format($r->rating) }}</span>
      </div>
      @if($r->answers)
        <details>
          <summary>{{ count($r->answers) }} {{ count($r->answers) === 1 ? 'answer' : 'answers' }}</summary>
          <ul>
            @foreach($r->answers as $a)
              <li><span>{{ $a['question'] ?? '' }}</span><b>{{ $a['score'] ?? '' }}/5</b></li>
            @endforeach
          </ul>
        </details>
      @elseif($r->comment)
        <p>{{ $r->comment }}</p>
      @endif
    </li>
  @endforeach
</ul>
