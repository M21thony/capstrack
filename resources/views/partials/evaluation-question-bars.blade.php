{{-- The average each question earned, as a bar out of 5.
     $questions comes from App\Support\EvaluationResults::byQuestion(). --}}
@if($questions->isEmpty())
  <p class="ev-hint">No question-by-question answers yet: these evaluations came before the questions did.</p>
@else
  <ul class="ev-qbars">
    @foreach($questions as $q)
      <li>
        <div class="ev-qbar-t"><span>{{ $q->question }}</span><b>{{ \App\Support\EvaluationResults::format($q->average) }}</b></div>
        <div class="ev-bar" aria-hidden="true"><i style="width: {{ round($q->average / 5 * 100) }}%"></i></div>
      </li>
    @endforeach
  </ul>
@endif
