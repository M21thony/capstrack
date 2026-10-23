{{--
  Team selection. It scrolls with the page: the header with the filters
  stays at the top while the cards go past, and Load more adds the next
  eight from TeamController::posts(). $selection comes from
  TeamManagementPage: page, counts, skills, goal.
--}}
@php
  $page = $selection['page'];
  $waiting = $invites->count() + $incomingRequests->count();
@endphp
@if ($selectionFolded)
  {{-- A member, or a leader whose team is full, has nobody to find: the
       section's posts wait behind one bar until asked for. --}}
  <section class="ts-fold" aria-labelledby="ts-fold-h">
    <span class="ts-fold-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg></span>
    <div class="ts-fold-text">
      <h2 id="ts-fold-h">Team selection</h2>
      <span>{{ $selection['counts']['all'] }} open {{ \Illuminate\Support\Str::plural('post', $selection['counts']['all']) }} in {{ $sectionLabel }} · {{ $teamedUp }} of {{ $totalStudents }} teamed up · {{ $waiting ? $waiting.' waiting on you' : 'No pending invites' }}</span>
    </div>
    <button type="button" class="ts-fold-toggle" data-ts-fold aria-expanded="false" aria-controls="team-selection">
      <span data-ts-fold-label>Browse posts</span>
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>
  </section>
@endif
<section class="ts" id="team-selection" aria-labelledby="ts-h" data-ts-url="{{ route('student.team.posts') }}" data-ts-goal="{{ $selection['goal'] }}" @if ($selectionFolded) hidden @endif>
  <div class="ts-head">
    <div class="ts-title">
      <h2 id="ts-h">Team selection</h2>
      <p>{{ $selection['subtitle'] }}</p>
      @if ($openRoles !== [])
        <div class="ts-needs"><span>Your team still needs</span>@foreach ($openRoles as $role)<b>{{ str_replace('/', ' / ', $role) }}</b>@endforeach</div>
      @endif
    </div>
    <div class="ts-filters">
      <div class="ts-tabs" role="group" aria-label="Filter posts">
        @foreach (\App\Support\TeamSelectionFeed::GOALS as $goal => $label)
          <button type="button" class="ts-tab" data-ts-goal="{{ $goal }}" aria-pressed="{{ $selection['goal'] === $goal ? 'true' : 'false' }}"><i class="ts-dot ts-dot-{{ $goal }}"></i>{{ $label }}<span class="ts-tab-n">{{ $selection['counts'][$goal] }}</span></button>
        @endforeach
      </div>
      @if ($selection['skills'] !== [])
        <div class="feed-filter ts-skill">
          <select class="feed-filter-select" id="feed-skill-filter" aria-label="Filter by skill">
            <option value="">Any skill</option>
            @foreach ($selection['skills'] as $skill)
              <option value="{{ $skill }}">{{ $skill }}</option>
            @endforeach
          </select>
        </div>
      @endif
    </div>
  </div>

  <div class="ts-body">
    <div class="ts-main">
      <div class="ts-grid" aria-label="Posts" data-ts-grid>
        @include('student.team._post-cards', ['posts' => $page->items()])
      </div>
      <div class="ts-empty" data-ts-empty @if ($page->total() > 0) hidden @endif>No posts here yet.</div>
      <div class="ts-more" data-ts-more-row @if (! $page->hasMorePages()) hidden @endif>
        <span>Showing <span data-ts-shown>{{ $page->lastItem() ?? 0 }}</span> of <span data-ts-total>{{ $page->total() }}</span></span>
        <button type="button" class="ts-btn" data-ts-more data-ts-next="2">Load more</button>
      </div>
    </div>
    <aside class="ts-aside" aria-labelledby="ts-overview-h">
      <h3 id="ts-overview-h">Section overview</h3>
      <div class="ts-big"><b>{{ $teamedUp }}</b> of {{ $totalStudents }} students teamed up</div>
      <div class="ts-bar" role="img" aria-label="{{ $teamedUp }} of {{ $totalStudents }} teamed up"><span style="width: {{ $totalStudents ? round($teamedUp / $totalStudents * 100) : 0 }}%"></span></div>
      <div class="ts-stats">
        <div><b>{{ $teamedUp }}</b><span>Teamed up</span></div>
        <div class="is-warm"><b>{{ $stillIndividual }}</b><span>Still individual{{ ! $myTeam ? ', including you' : '' }}</span></div>
      </div>
    </aside>
  </div>
</section>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  var root = document.getElementById('team-selection');
  if (!root) return;
  var fold = document.querySelector('[data-ts-fold]');
  if (fold) {
    fold.addEventListener('click', function () {
      var open = root.hidden;
      root.hidden = !open;
      fold.setAttribute('aria-expanded', open ? 'true' : 'false');
      fold.querySelector('[data-ts-fold-label]').textContent = open ? 'Hide posts' : 'Browse posts';
      fold.closest('.ts-fold').classList.toggle('is-open', open);
    });
  }
  var grid = root.querySelector('[data-ts-grid]');
  var empty = root.querySelector('[data-ts-empty]');
  var moreRow = root.querySelector('[data-ts-more-row]');
  var more = root.querySelector('[data-ts-more]');
  var skillSelect = root.querySelector('#feed-skill-filter');
  var goal = root.getAttribute('data-ts-goal');

  function load(page, append) {
    var url = new URL(root.getAttribute('data-ts-url'), location.href);
    url.searchParams.set('goal', goal);
    url.searchParams.set('page', page);
    if (skillSelect && skillSelect.value) url.searchParams.set('skill', skillSelect.value);
    more.disabled = true;
    return fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('Could not load posts.'); return r.json(); })
      .then(function (data) {
        if (append) { grid.insertAdjacentHTML('beforeend', data.html); } else { grid.innerHTML = data.html; }
        root.querySelector('[data-ts-shown]').textContent = data.shown;
        root.querySelector('[data-ts-total]').textContent = data.total;
        more.setAttribute('data-ts-next', data.nextPage);
        moreRow.hidden = !data.hasMore;
        empty.hidden = data.total > 0;
        Object.keys(data.counts).forEach(function (g) {
          var n = root.querySelector('[data-ts-goal="' + g + '"] .ts-tab-n');
          if (n) n.textContent = data.counts[g];
        });
      })
      .catch(function (e) { if (typeof showToast === 'function') showToast(e.message, 'error'); })
      .then(function () { more.disabled = false; });
  }

  root.querySelectorAll('[data-ts-goal]').forEach(function (tab) {
    if (tab === root) return;
    tab.addEventListener('click', function () {
      goal = tab.getAttribute('data-ts-goal');
      root.querySelectorAll('button[data-ts-goal]').forEach(function (b) { b.setAttribute('aria-pressed', b === tab ? 'true' : 'false'); });
      load(1, false);
    });
  });
  if (skillSelect) skillSelect.addEventListener('change', function () { load(1, false); });
  more.addEventListener('click', function () { load(+more.getAttribute('data-ts-next'), true); });
})();
</script>
