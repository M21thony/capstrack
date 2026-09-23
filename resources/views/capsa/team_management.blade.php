<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Team Management - CapsTRACK Admin</title>
<meta name="csrf-token" content="{{ csrf_token() }}"/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}"/>
<link rel="stylesheet" href="{{ asset('css/team-management.css') }}"/>
<style>
  .tm-empty{text-align:center;padding:44px 20px;color:var(--text3);font-size:13px;line-height:1.6}
</style>
@include('partials.skeleton-head', ['skReveal' => 'flex'])
</head>
<body>

@include('capsa._sidebar')
@include('partials.sidebar-toggle')
<div class="main">
  <div class="topbar">
    <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="page-title">Team Management</div>
    <div class="topbar-actions">
      @include('capsa.partials.notification-dropdown')
    </div>
  </div>

  <div id="skeleton-content" class="sk-overlay">
    <div class="card" style="padding:16px">
      <div class="sk" style="width:280px;height:34px;border-radius:8px;margin-bottom:16px"></div>
      @for ($i = 0; $i < 4; $i++)
        <div class="sk" style="height:44px;border-radius:8px;margin-bottom:10px"></div>
      @endfor
    </div>
  </div>

  <div id="real-content" style="display:none;flex:1;overflow:hidden;flex-direction:column;">
  <div class="content">

    @if (session('status'))
      <div class="card" style="padding:12px 16px;margin-bottom:14px;border-left:3px solid var(--green);font-size:12.5px;color:var(--text2)">{!! session('status') !!}</div>
    @endif
    @if (session('error'))
      <div class="card" style="padding:12px 16px;margin-bottom:14px;border-left:3px solid var(--red);font-size:12.5px;color:var(--text2)">{!! session('error') !!}</div>
    @endif

    @if ($tab !== 'teams')

      {{-- The same trail as every other level, so these three pages are named
           and have a way back, instead of opening with no title on them. --}}
      @include('capsa.partials.team-management._crumb', ['trail' => [
        ['Team Management', route('capsa.team_management')],
        [$tab === 'milestones' ? 'Milestones' : 'Leave Requests', null],
      ]])
      @include('capsa.partials.team-management.' . $tab, $tabData)

    @elseif ($allSections === [])

      <div class="card"><div class="tm-empty">
        You have no sections assigned yet, so there are no teams to manage.<br>
        An Admin assigns sections when they make someone a CaPSA.
      </div></div>

    @else

    {{-- No `use` here: this block sits inside @else, and PHP only allows
         imports at the top level of a file. Both classes are written out. --}}
    @php
      $maxMembers = \App\Models\Team::maxMembers();
      $roleColours = ['adviser' => '#1d4ed8', 'consultant' => '#0891b2', 'grammarian' => '#b45309'];

      // Said once, so the Status column, the Status filter and the tiles
      // cannot disagree about what a team's state is called.
      $statusOf = fn ($r) => $r->team->is_confirmed ? 'Verified'
        : ($r->awaitingVerification ? 'To verify' : ucfirst($r->team->team_status));
      $statusPill = ['Verified' => 'p-green', 'To verify' => 'p-amber'];

      $isDone = fn ($r) => $r->milestones && $r->milestones->total > 0
        && $r->milestones->completed === $r->milestones->total;
      $needsHer = fn ($r) => $r->pendingProposalCount > 0 || $r->awaitingVerification || $r->papersToCheck > 0;

      $completedCount = $rows->filter($isDone)->count();
      $reviewCount = $rows->reject($isDone)->filter($needsHer)->count();

      // One column per milestone name, in the order the checklists first
      // mention them, so sections that share a checklist line up and one that
      // does not still gets its own columns rather than the wrong team's.
      // ponytail: the header's due date and lock come from the first section
      // that has that milestone. Split the grid per section if two checklists
      // ever disagree about them.
      $columns = [];
      foreach ($rows as $r) {
        foreach ($r->milestones->milestones ?? [] as $ms) {
          $columns[$ms->milestone->name] ??= [
            'due' => $ms->due_on,
            'gated' => (bool) $ms->milestone->requires_previous,
          ];
        }
      }

      // Where her decisions wait, in one line.
      $waiting = [];
      $n = $counts['pre_proposals'] ?? 0;
      if ($n > 0) { $waiting[] = $n.' '.Str::plural('title', $n).' to review'; }
      $n = $counts['verification'] ?? 0;
      if ($n > 0) { $waiting[] = $n.' to verify'; }
      $n = $counts['papers'] ?? 0;
      if ($n > 0) { $waiting[] = $n.' '.Str::plural('paper', $n).' to check'; }
      $n = $counts['leave_requests'] ?? 0;
      if ($n > 0) { $waiting[] = $n.' '.Str::plural('request', $n).' waiting'; }
      $waiting = implode(' &middot; ', $waiting);
    @endphp

    <div class="tmx">
      {{-- The same trail every level below uses, so the list is plainly the
           top of it rather than a page with no place in the trail. --}}
      @include('capsa.partials.team-management._crumb', ['trail' => [['Team Management', null]]])

      <div class="phead">
        <div class="tile"><x-tm-icon n="users" s="22" /></div>
        <div style="min-width:0">
          <h1>Team Management</h1>
          <p>{!! $waiting !== '' ? '<b>Waiting on you:</b> '.$waiting : 'Every team in your sections, and everything about them, on one page.' !!}</p>
        </div>
        {{-- Milestones and Leave Requests are lists of their own, so they are
             pages. The three team rules are three small answers, so they open
             in a menu under their button rather than sending her away. --}}
        <div class="pacts">
          <a class="btn btn-o" href="{{ route('capsa.team_management', ['tab' => 'milestones']) }}">
            <x-tm-icon n="clip" s="16" />Milestones
            @if (($counts['milestones'] ?? 0) > 0)<span class="nb">{{ $counts['milestones'] }} to check</span>@endif
          </a>
          <a class="btn btn-o" href="{{ route('capsa.team_management', ['tab' => 'leave_requests']) }}">
            <x-tm-icon n="mail" s="16" />Leave Requests
            @if (($counts['leave_requests'] ?? 0) > 0)<span class="nb">{{ $counts['leave_requests'] }}</span>@endif
          </a>

          <div class="setw">
            <button class="btn btn-o" type="button" id="setBtn" aria-expanded="false" aria-controls="setMenu">
              <x-tm-icon n="gear" s="16" />Team Settings
            </button>
            <div class="menu" id="setMenu" hidden>
              <h3>Team Settings</h3>
              <label class="opt" for="tsize">
                <span><b>Max members per team</b><small>Any number from {{ \App\Models\Team::MIN_MEMBERS }} to {{ \App\Models\Team::MAX_MEMBERS }}</small></span>
                <input type="number" id="tsize" min="{{ \App\Models\Team::MIN_MEMBERS }}" max="{{ \App\Models\Team::MAX_MEMBERS }}" step="1" inputmode="numeric">
              </label>
              <p class="opt-w" id="tsw" hidden></p>
              <label class="opt" for="acreate">
                <span><b>Allow students to create teams</b><small>Students can start a new team</small></span>
                <input type="checkbox" class="tg" role="switch" id="acreate">
              </label>
              <label class="opt" for="ajoin">
                <span><b>Allow students to join teams</b><small>Students can ask to join an existing team</small></span>
                <input type="checkbox" class="tg" role="switch" id="ajoin">
              </label>
              <div class="mact">
                <button class="btn btn-o" type="button" id="setX">Cancel</button>
                <button class="btn btn-p" type="button" id="setOk">Save</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="stats">
        <div class="stat">
          <div><div class="lab">Total Teams</div><div class="num">{{ $rows->count() }}</div></div>
          <div class="tile" style="background:var(--accent-bg);color:var(--accent3)"><x-tm-icon n="users" s="20" /></div>
        </div>
        <div class="stat">
          <div><div class="lab">In Progress</div><div class="num">{{ $rows->count() - $completedCount - $reviewCount }}</div></div>
          <div class="tile" style="background:var(--amber-bg);color:var(--amber)"><x-tm-icon n="clock" s="20" /></div>
        </div>
        <div class="stat">
          <div><div class="lab">For Review</div><div class="num">{{ $reviewCount }}</div></div>
          <div class="tile" style="background:var(--violet-bg);color:var(--violet)"><x-tm-icon n="file" s="20" /></div>
        </div>
        <div class="stat">
          <div><div class="lab">Completed</div><div class="num">{{ $completedCount }}</div></div>
          <div class="tile" style="background:var(--green-bg);color:var(--green)"><x-tm-icon n="check" s="20" /></div>
        </div>
      </div>

      <div class="tools">
        <label class="fs">
          <x-tm-icon n="search" s="16" />
          <input type="text" id="tm-q" placeholder="Search by team name, title, or member..." aria-label="Search teams">
        </label>

        {{-- Section stays a real page load: it decides which teams are here
             at all, not just which of them are shown. --}}
        <form method="get" action="{{ route('capsa.team_management') }}" style="display:contents">
          <select class="sel" name="section" aria-label="Section" onchange="this.form.submit()">
            <option value="">All Sections</option>
            @foreach ($sectionOptions as $section)
              <option value="{{ $section }}" @selected($selectedSection === $section)>{{ $section }}</option>
            @endforeach
          </select>
        </form>

        <select class="sel" id="tm-st" aria-label="Status">
          <option value="">All Status</option>
          @foreach ($rows->map($statusOf)->unique()->sort() as $status)
            <option value="{{ $status }}">{{ $status }}</option>
          @endforeach
        </select>

        <div class="seg" role="group" aria-label="Show">
          <button type="button" data-v="teams" aria-pressed="true">Teams</button>
          <button type="button" data-v="progress" aria-pressed="false">Progress</button>
        </div>
      </div>

      <div class="tcard" id="tm-teams">
        <div class="tscroll"><table>
          <thead><tr>
            <th style="width:44px"><input type="checkbox" id="tm-all" aria-label="Select all teams"></th>
            <th>Team</th><th>Section</th><th>Title</th><th>Experts</th>
            <th>Milestones</th><th>Defense Score</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
            @foreach ($rows as $row)
              @php
                $status = $statusOf($row);
                $href = route('capsa.team_management.show', $row->team->team_id);
                $haystack = Str::lower(collect([
                  $row->team->team_name, $row->team->project_title, $row->section, $row->leaderName,
                ])->merge($row->members->map(fn ($m) => trim(($m->f_name ?? '').' '.($m->l_name ?? ''))))
                  ->filter()->implode(' '));
                $score = $defenseScores->get($row->team->team_id, collect())->last(fn ($s) => $s->average !== null);
              @endphp
              <tr data-q="{{ $haystack }}" data-st="{{ $status }}" data-href="{{ $href }}">
                <td class="tm-check"><input type="checkbox" aria-label="Select {{ $row->team->team_name }}"></td>

                <td data-label="Team"><div style="display:flex;align-items:center;gap:11px">
                  <div class="av" style="background:linear-gradient(135deg,{{ $row->gradient }})">{{ $row->initials }}</div>
                  <div style="min-width:0">
                    <div class="tname">{{ $row->team->team_name }}</div>
                    <div class="tsub">{{ $row->members->count() }}/{{ $maxMembers }} members &middot; {{ $row->leaderName }}</div>
                  </div>
                </div></td>

                <td data-label="Section"><span class="pill {{ $row->section === \App\Support\SectionScope::UNASSIGNED ? 'p-gray' : 'p-gold' }}">{{ $row->section }}</span></td>

                <td style="max-width:210px" data-label="Title"><div style="font-size:12.5px;line-height:1.45">
                  @if ($row->team->project_title)
                    {{ $row->team->project_title }}
                  @else
                    <span class="tsub">{{ [
                      'awaiting_team_choice' => $row->approvedProposalCount.' recommended - the team is choosing',
                      'settling' => 'One title recommended',
                      'awaiting_review' => 'Waiting on your review',
                    ][$row->titleState] ?? 'No titles yet' }}</span>
                  @endif
                </div></td>

                <td data-label="Experts"><div class="chips" aria-label="{{ $row->engagedRoles->count() }} of 3 experts">
                  @foreach ($roleColours as $role => $colour)
                    @php $who = $row->engagedRoles->get($role); @endphp
                    @if ($who)
                      <span style="background:{{ $colour }}" title="{{ ucfirst($role) }}: {{ $who->name }}">{{ collect(preg_split('/\s+/', trim($who->name)))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('') }}</span>
                    @else
                      <span class="more" title="No {{ $role }} yet">&ndash;</span>
                    @endif
                  @endforeach
                </div></td>

                <td data-label="Milestones">
                  @if ($row->milestones && $row->milestones->total > 0)
                    <div style="display:flex;align-items:center;gap:9px">
                      <div class="bar {{ $row->milestones->overdue ? 'od' : '' }}"><i style="width:{{ $row->milestones->percent }}%"></i></div>
                      <span class="barn">{{ $row->milestones->completed }}/{{ $row->milestones->total }}</span>
                    </div>
                    @if ($row->milestones->overdue)
                      <div class="od-note">{{ $row->milestones->overdue }} overdue</div>
                    @endif
                  @else
                    <span class="tsub">No checklist yet</span>
                  @endif
                </td>

                <td data-label="Defense Score">
                  @if ($score)
                    <b style="color:var(--ink);font-size:13.5px;font-variant-numeric:tabular-nums">{{ \App\Support\DefenseScores::format($score->average) }}</b>
                    <div class="tsub">{{ $score->label }}</div>
                  @else
                    <span class="tsub">{{ $row->currentStage ?: 'Not scheduled' }}</span>
                  @endif
                </td>

                <td data-label="Status">
                  <span class="pill {{ $statusPill[$status] ?? 'p-gray' }}">{{ $status }}</span>
                  {{-- Missing a stage's window is a fact about where they are,
                       not a second status, so it sits under the pill. --}}
                  @if ($row->failedStage)
                    <div class="od-note">Missed {{ Str::before($row->failedStage, ' Defense') }} deadline</div>
                  @elseif ($row->papersToCheck)
                    <div class="tsub">{{ $row->papersToCheck }} {{ Str::plural('paper', $row->papersToCheck) }} to check</div>
                  @endif
                </td>

                <td data-label="Actions"><a class="vd" href="{{ $href }}"><x-tm-icon n="user" s="14" />View Details</a></td>
              </tr>
            @endforeach
            <tr class="tm-none" @if ($rows->isNotEmpty()) hidden @endif>
              <td colspan="9" style="text-align:center;padding:46px 20px;color:var(--mut)">
                @if ($rows->isEmpty())
                  No teams in {{ $selectedSection ? 'section '.$selectedSection : 'your sections' }} yet.
                @else
                  No teams match that search.
                @endif
              </td>
            </tr>
          </tbody>
        </table></div>
        <div class="tfoot"><span class="c" data-count>{{ $rows->count() }} {{ Str::plural('team', $rows->count()) }}</span></div>
      </div>

      <div class="tcard" id="tm-progress" hidden>
        <div class="tscroll"><table class="pg">
          <thead><tr>
            <th>Team</th>
            @foreach ($columns as $name => $col)
              <th>{{ $name }}<small>
                @if ($col['gated'])<x-tm-icon n="lock" s="11" />@endif
                {{ $col['due'] ? 'Due '.\Illuminate\Support\Carbon::parse($col['due'])->format('M j') : 'No due date' }}
              </small></th>
            @endforeach
            <th>Overall</th>
          </tr></thead>
          <tbody>
            @foreach ($rows as $row)
              @php
                // Keyed by name so a team lands in the right column even when
                // its section's checklist is shorter than another's.
                $byName = collect($row->milestones->milestones ?? [])
                  ->mapWithKeys(fn ($ms, $i) => [$ms->milestone->name => [$ms, $i]]);
              @endphp
              <tr data-q="{{ Str::lower($row->team->team_name.' '.$row->section) }}" data-st="{{ $statusOf($row) }}">
                <td><div style="display:flex;align-items:center;gap:10px">
                  <div class="av" style="background:linear-gradient(135deg,{{ $row->gradient }})">{{ $row->initials }}</div>
                  <div><div class="tname">{{ $row->team->team_name }}</div><div class="tsub">{{ $row->section }}</div></div>
                </div></td>

                @foreach ($columns as $name => $col)
                  <td>
                    @if ($byName->has($name))
                      @php
                        [$ms, $i] = $byName[$name];
                        [$word, , $icon] = \App\Support\MilestoneProgress::milestoneState($ms);
                        $tone = ['Completed' => 'd', 'Overdue' => 'o', 'In progress' => 'p', 'Locked' => 'k'][$word] ?? 'n';
                      @endphp
                      <a class="cell c-{{ $tone }}"
                         href="{{ route('capsa.team_management.show', [$row->team->team_id, 'milestone' => $i]) }}"
                         aria-label="{{ $row->team->team_name }}, {{ $name }}: {{ $word }}{{ $ms->locked ? '' : ", {$ms->completed} of {$ms->total} done" }}">
                        <span class="cn"><x-tm-icon :n="$icon" s="13" />{{ $ms->locked ? '' : "{$ms->completed}/{$ms->total}" }}</span>
                        <span class="cw">{{ $word }}</span>
                      </a>
                    @else
                      <span class="tsub" style="display:block;text-align:center">&ndash;</span>
                    @endif
                  </td>
                @endforeach

                <td><div style="display:flex;align-items:center;gap:8px">
                  <div class="bar {{ $row->milestones?->overdue ? 'od' : '' }}"><i style="width:{{ $row->milestones->percent ?? 0 }}%"></i></div>
                  <span class="barn">{{ $row->milestones->completed ?? 0 }}/{{ $row->milestones->total ?? 0 }}</span>
                </div></td>
              </tr>
            @endforeach
            <tr class="tm-none" @if ($rows->isNotEmpty()) hidden @endif>
              <td colspan="{{ count($columns) + 2 }}" style="text-align:center;padding:46px 20px;color:var(--mut)">
                No teams match that search.
              </td>
            </tr>
          </tbody>
        </table></div>
        <div class="legend">
          <span><i class="c-d"></i>Completed</span>
          <span><i class="c-p"></i>In progress</span>
          <span><i class="c-o"></i>Overdue</span>
          <span><i class="c-n"></i>Not started</span>
          <span><i class="c-k"></i>Locked</span>
          <span><x-tm-icon n="lock" s="12" />Opens only after the milestone before it is complete</span>
        </div>
      </div>
    </div>

    @endif

  </div>
  </div>
</div>
@include('capsa.partials.skeleton-toggle')
@include('partials.app-dialog')
<div id="tm-toast" class="toast"></div>

@if ($tab === 'teams' && $allSections !== [])
<script>
  (function () {
    /* Search and Status hide rows in both tables at once. Section is a real
       page load, because it decides which teams are on the page at all. */
    const box = document.getElementById('tm-q'), status = document.getElementById('tm-st'),
          rows = [...document.querySelectorAll('tr[data-q]')];

    const apply = () => {
      const q = box.value.trim().toLowerCase(), st = status.value;
      rows.forEach(r => { r.hidden = !(r.dataset.q.includes(q) && (!st || r.dataset.st === st)); });

      document.querySelectorAll('tbody').forEach(b => {
        const shown = [...b.querySelectorAll('tr[data-q]')].filter(r => !r.hidden).length;
        const none = b.querySelector('.tm-none');
        if (none) none.hidden = shown > 0;
      });

      const shown = [...document.querySelectorAll('#tm-teams tr[data-q]')].filter(r => !r.hidden).length;
      document.querySelector('[data-count]').textContent = shown + (shown === 1 ? ' team' : ' teams');
    };
    box.oninput = apply;
    status.onchange = apply;

    /* Anywhere on the row opens the team, except the parts that do their own thing. */
    document.querySelectorAll('tr[data-href]').forEach(r => r.onclick = e => {
      if (!e.target.closest('input,a,button')) location.href = r.dataset.href;
    });

    const all = document.getElementById('tm-all');
    all.onchange = () => document.querySelectorAll('#tm-teams tbody tr[data-q]').forEach(r => {
      if (!r.hidden) r.querySelector('input[type=checkbox]').checked = all.checked;
    });

    document.querySelectorAll('.seg button').forEach(b => b.onclick = () => {
      document.querySelectorAll('.seg button').forEach(x => x.setAttribute('aria-pressed', x === b));
      document.getElementById('tm-teams').hidden = b.dataset.v !== 'teams';
      document.getElementById('tm-progress').hidden = b.dataset.v !== 'progress';
    });

    /* Team Settings: opens under its button. Cancel, Escape or clicking away
       drops the changes, so nothing is saved by accident. */
    const RULES = @json(['max' => $rules['maxMembers'], 'create' => $rules['allowCreation'], 'join' => $rules['allowJoining']]),
          SIZES = @json((object) $rules['teamSizes']),
          MIN = {{ \App\Models\Team::MIN_MEMBERS }}, MAX = {{ \App\Models\Team::MAX_MEMBERS }};
    const setBtn = document.getElementById('setBtn'), setMenu = document.getElementById('setMenu'),
          tsize = document.getElementById('tsize'), tsw = document.getElementById('tsw'),
          acreate = document.getElementById('acreate'), ajoin = document.getElementById('ajoin'),
          setOk = document.getElementById('setOk');

    const toast = (msg, ok) => {
      const el = document.getElementById('tm-toast');
      el.textContent = msg;
      el.style.borderLeftColor = ok ? 'var(--green)' : 'var(--red)';
      el.classList.add('show');
      clearTimeout(toast.t);
      toast.t = setTimeout(() => el.classList.remove('show'), 3200);
    };

    const checkSize = () => {
      const v = Number(tsize.value), ok = Number.isInteger(v) && v >= MIN && v <= MAX;
      /* How many teams a lower limit would reach, said before she commits. */
      const over = ok ? Object.keys(SIZES).reduce((n, k) => n + (Number(k) > v ? SIZES[k] : 0), 0) : 0;
      tsize.setAttribute('aria-invalid', !ok);
      tsw.className = 'opt-w' + (ok ? ' soft' : '');
      tsw.textContent = !ok
        ? 'Type a number from ' + MIN + ' to ' + MAX + '.'
        : over + ' team' + (over > 1 ? 's' : '') + ' already ' + (over > 1 ? 'have' : 'has')
          + ' more than ' + v + '. They keep their members but cannot add anyone.';
      tsw.hidden = ok && !over;
      setOk.disabled = !ok;
    };

    const setOpen = on => {
      if (on) { tsize.value = RULES.max; acreate.checked = RULES.create; ajoin.checked = RULES.join; checkSize(); }
      setMenu.hidden = !on;
      setBtn.setAttribute('aria-expanded', on);
      if (on) tsize.focus();
    };

    setBtn.onclick = () => setOpen(setMenu.hidden);
    tsize.oninput = checkSize;
    document.getElementById('setX').onclick = () => { setOpen(false); setBtn.focus(); };
    document.addEventListener('click', e => { if (!setMenu.hidden && !e.target.closest('.setw')) setOpen(false); });
    setMenu.addEventListener('keydown', e => { if (e.key === 'Escape') { setOpen(false); setBtn.focus(); } });

    setOk.onclick = () => {
      setOk.disabled = true;
      fetch(@json(route('capsa.team_rules.update'), JSON_UNESCAPED_SLASHES), {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
          max_members: Number(tsize.value),
          allow_creation: acreate.checked,
          allow_joining: ajoin.checked,
        }),
      })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
          if (!ok || !data.success) { toast(data.message || 'Could not save the team settings.', false); return; }
          RULES.max = data.max_members; RULES.create = data.allow_creation; RULES.join = data.allow_joining;
          setOpen(false);
          setBtn.focus();
          toast(data.message, true);
        })
        .catch(() => toast('Network error. Nothing was saved.', false))
        .finally(() => { setOk.disabled = false; });
    };
  })();
</script>
@endif

@include('partials.escape-closes-modals')
</body>
</html>
