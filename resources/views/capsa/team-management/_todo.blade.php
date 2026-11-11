{{--
  The To do tab: every decision waiting on this person, in one list.

  Grouped by kind, the item that has waited longest first in each group, and
  anything waiting three days or more says so in red. Completed keeps what was
  settled lately, so an item does not just vanish.
--}}
@php
  $icons = ['go' => 'cal', 'go_given' => 'check', 'title_change' => 'file', 'title' => 'file', 'adviser_title' => 'file', 'verify' => 'shield', 'paper' => 'clip', 'signed' => 'star',
            'review' => 'file', 'leave' => 'users', 'expert' => 'user', 'invite' => 'mail', 'grade' => 'trophy'];
  $hatName = fn ($hat) => $hat === 'capsa' ? 'CaPSA/TSA' : ucfirst($hat);
  $resultTone = fn ($r) => preg_match('/Approved|Verified|Accepted|Checked|Recommended|Reviewed|Submitted/', $r) ? 'ok'
    : (preg_match('/Turned|Declined|Rejected/', $r) ? 'bad' : ($r === 'Needs revision' ? 'warn' : 'idle'));
  $doneKinds = ['paper' => 'Paper', 'invite' => 'Invitation', 'decision' => 'Request'];
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
        @if ($group === \App\Support\TeamTodo::GROUPS['invite'])
          {{-- A shortcut: the whole list, with what was declined, is the
               Team Applications tab. --}}
          <div class="group-h group-apps">
            <span>{{ $group }}</span>
            <a class="see-all" href="{{ route($hubRoute, ['tab' => 'applications']) }}" data-hub-tab="applications">See all</a>
          </div>
        @else
          <div class="group-h">{{ $group }}</div>
        @endif
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
                @if ($item->titleChange)
                  <dl class="tc-diff">
                    <dt>Now</dt><dd>{{ $item->titleChange['now'] }}</dd>
                    <dt>Change to</dt><dd class="nw">{{ $item->titleChange['next'] }}</dd>
                  </dl>
                @endif
              </div>
              <div class="acts">
                @if ($item->read)
                  <button type="button" class="btn rd" data-read="{{ $item->read[0] }}" data-read-id="{{ $item->read[1] }}">
                    <x-tm-icon n="file" s="15" />{{ $item->read[0] === 'title' ? 'Read title' : 'Read application' }}
                  </button>
                @endif
                @if ($item->decide)
                  @php $decide = $item->decide; $declineLabel = $decide['declineLabel'] ?? 'Decline'; @endphp
                  <button type="button" class="btn" data-decide="decline"
                          data-url="{{ $decide['url'] }}" data-field="{{ $decide['field'] }}" data-value="{{ $decide['decline'] }}"
                          data-note="{{ $decide['note'] }}" data-min="{{ $decide['min'] }}"
                          @if (! empty($decide['noteOptional'])) data-note-optional="1" @endif
                          @if (! empty($decide['declineEffect'])) data-effect="{{ $decide['declineEffect'] }}" @endif
                          data-title="{{ $decide['declineTitle'] ?? $declineLabel.': '.$item->what }}" data-sub="Team {{ $item->teamName }}">{{ $declineLabel }}</button>
                  @unless (! empty($decide['declineOnly']))
                    <button type="button" data-decide="approve"
                            data-url="{{ $decide['url'] }}" data-field="{{ $decide['field'] }}" data-value="{{ $decide['approve'] }}"
                            data-note="{{ $decide['note'] }}" data-effect="{{ $decide['effect'] }}"
                            data-title="{{ $item->what }}" data-sub="Team {{ $item->teamName }} · {{ $item->why }}" class="btn p">{{ $item->label }}</button>
                  @endunless
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

  </div>

  {{-- Completed: everything settled, newest first, under a heading per month.
       Only the latest two months show; the button under them adds one older
       month at a time, and the search looks through every month. --}}
  <div id="tm-done" hidden>
    @unless ($decided->isEmpty())
      <div class="done-bar">
        <span id="tm-done-total" aria-live="polite"></span>
        <label class="search"><x-tm-icon n="search" s="15" />
          <input type="search" id="tm-done-q" placeholder="Search team or paper" aria-label="Search completed" autocomplete="off"></label>
      </div>
    @endunless
    <div class="done-box" id="tm-done-box" tabindex="0">
      <div id="tm-todo-decided">
        @if ($decided->isEmpty())
          <div class="empty"><b>Nothing completed yet</b>Papers you review and what you approve or decline show up here.</div>
        @else
          @foreach ($decided->groupBy(fn ($done) => $done->at->format('Y-m')) as $month)
            <section data-month="{{ $month->first()->at->format('F Y') }}" data-n="{{ $month->count() }}">
              <h3 class="mon">{{ $month->first()->at->format('F Y') }} <span class="n">· {{ $month->count() }}</span></h3>
              <ul class="todo hist">
                @foreach ($month as $done)
                  <li data-q="{{ \Illuminate\Support\Str::lower($done->what.' '.$done->teamName.' '.$doneKinds[$done->kind].' '.$done->detail) }}">
                    <time class="day" datetime="{{ $done->at->toDateString() }}">{{ $done->at->format('M j') }}<small>{{ $done->at->format('D') }}</small></time>
                    <span class="ico k-{{ $done->kind }}"><x-tm-icon :n="$icons[$done->kind] ?? 'shield'" s="18" /></span>
                    <div class="what"><b>{{ $done->what }}</b>
                      <span>@if ($done->teamName)Team {{ $done->teamName }} · @endif{{ $doneKinds[$done->kind] }}@if ($done->detail) · {{ $done->detail }}@endif</span></div>
                    <div class="acts">
                      <span class="state st-{{ $resultTone($done->result) }}">{{ $done->result }}</span>
                      @if ($done->url)<a class="btn" href="{{ $done->url }}" data-done-open>Open</a>@else<span class="btn-gap"></span>@endif
                    </div>
                  </li>
                @endforeach
              </ul>
            </section>
          @endforeach
          <div class="empty" id="tm-done-none" hidden><b>Nothing matches</b>Try a team or paper name.</div>
          <div class="more" id="tm-done-more-row"><button type="button" class="btn" id="tm-done-more"></button></div>
          <p class="end" id="tm-done-end" hidden></p>
        @endif
      </div>
    </div>
  </div>
</div>
