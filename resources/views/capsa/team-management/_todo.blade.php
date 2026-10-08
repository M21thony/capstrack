{{--
  The To do tab: every decision waiting on this person, in one list.

  Grouped by kind, the item that has waited longest first in each group, and
  anything waiting three days or more says so in red. Completed keeps what was
  settled lately, so an item does not just vanish.
--}}
@php
  $icons = ['title' => 'file', 'adviser_title' => 'file', 'verify' => 'shield', 'paper' => 'clip', 'signed' => 'star',
            'review' => 'file', 'leave' => 'users', 'expert' => 'user', 'invite' => 'mail', 'grade' => 'trophy'];
  $hatName = fn ($hat) => $hat === 'capsa' ? 'CaPSA' : ucfirst($hat);
  $resultTone = fn ($r) => preg_match('/Approved|Verified|Accepted|Checked|Recommended|Reviewed|Submitted/', $r) ? 'ok'
    : (preg_match('/Turned|Declined|Rejected/', $r) ? 'bad' : 'idle');
  $hubRoute = $portal === 'capsa' ? 'capsa.team_management' : 'instructor.teams';
@endphp

<div class="tools">
  <div class="seg" role="group" aria-label="Show">
    <button type="button" data-todo-mode="waiting" aria-pressed="true">Pending</button>
    <button type="button" data-todo-mode="decided" aria-pressed="false">Completed</button>
  </div>
</div>

<div class="card">
  <div>
    <div id="tm-todo-waiting">
      @forelse (\App\Support\TeamTodo::grouped($todo) as $group => $items)
        <div class="group-h">{{ $group }}</div>
        <ul class="todo">
          @foreach ($items as $item)
            <li data-item>
              <span class="ico k-{{ $item->kind }}"><x-tm-icon :n="$icons[$item->kind] ?? 'dot'" s="18" /></span>
              <div class="what">
                <b>@if ($item->fresh)<i class="new" aria-label="New"></i>@endif{{ $item->what }}</b>
                <span>
                  @if ($item->teamUrl)<a class="team" href="{{ $item->teamUrl }}">Team {{ $item->teamName }}</a>@else Team {{ $item->teamName }}@endif
                  @if ($item->section) · {{ $item->section }}@endif
                  @if ($twoHats) · <b class="hat">{{ $hatName($item->hat) }}</b>@endif
                  · {{ $item->why }} ·
                  @if ($item->age >= \App\Support\TeamTodo::LATE_AFTER_DAYS)
                    <span class="late">Waiting {{ $item->age }} days</span>
                  @elseif ($item->at)
                    <time datetime="{{ $item->at->toIso8601String() }}">{{ $item->at->isFuture() ? $item->at->format('M j') : $item->at->diffForHumans() }}</time>
                  @endif
                </span>
              </div>
              <div class="acts">
                @if ($item->decide)
                  @php $decide = $item->decide; @endphp
                  <button type="button" class="btn" data-decide="decline"
                          data-url="{{ $decide['url'] }}" data-field="{{ $decide['field'] }}" data-value="{{ $decide['decline'] }}"
                          data-note="{{ $decide['note'] }}" data-min="{{ $decide['min'] }}"
                          data-title="Decline: {{ $item->what }}" data-sub="Team {{ $item->teamName }}">Decline</button>
                  <button type="button" data-decide="approve"
                          data-url="{{ $decide['url'] }}" data-field="{{ $decide['field'] }}" data-value="{{ $decide['approve'] }}"
                          data-note="{{ $decide['note'] }}" data-effect="{{ $decide['effect'] }}"
                          data-title="{{ $item->what }}" data-sub="Team {{ $item->teamName }} · {{ $item->why }}" class="btn p">{{ $item->label }}</button>
                @elseif ($item->kind === 'verify')
                  <a class="btn" href="{{ $item->teamUrl }}">Open team</a>
                  <a class="btn p" href="{{ $item->url }}">{{ $item->label }}</a>
                @else
                  <a class="btn p" href="{{ $item->url }}">{{ $item->label }}</a>
                @endif
              </div>
            </li>
          @endforeach
        </ul>
      @empty
        <div class="empty">
          <b>All caught up</b>
          Nothing to do right now. New titles, papers and requests show up here.<br><br>
          <a class="btn" href="{{ route($hubRoute, ['tab' => 'teams', 'quick' => 'behind']) }}" data-hub-tab="teams" data-quick-go="behind">See overdue teams</a>
        </div>
      @endforelse
    </div>

    <div id="tm-todo-decided" hidden>
      @if ($decided->isEmpty())
        <div class="empty"><b>Nothing completed yet</b>What you approve, decline or check shows up here.</div>
      @else
        <ul class="todo">
          @foreach ($decided as $done)
            <li>
              <span class="ico"><x-tm-icon n="check" s="18" /></span>
              <div class="what"><b>{{ $done->what }}</b>
                <span>@if ($done->teamName)Team {{ $done->teamName }} · @endif{{ $done->at->format('M j, Y') }}</span></div>
              <div class="acts"><span class="state st-{{ $resultTone($done->result) }}">{{ $done->result }}</span></div>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </div>
</div>
