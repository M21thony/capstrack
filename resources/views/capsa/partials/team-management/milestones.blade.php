{{-- Milestones: every team in the CaPSA's sections against every deliverable
     on their checklist.

     Deliverables run across and teams run down, because the nineteen are a
     fixed list and the teams are not - a CaPSA with three sections has around
     two dozen, and a page grows downwards far more gracefully than sideways.
     It also puts a team's name on its own row, so which team a cell belongs to
     never has to be counted across.

     One screen builds the checklist and ticks the papers off it, because a
     CaPSA working down a section does both in the same sitting. --}}

<div class="ms-intro">
  <div>
    <div class="ms-intro-title">Checklists</div>
    <div class="ms-intro-note">
      You check the <strong>signed forms</strong> here: open what the team uploaded, then mark it checked.
      A draft closes when the team uploads it, a CLF when the adviser or consultant approves it in
      Document Review, and a defense when the panel's decision is recorded &mdash; those are drawn
      dashed and say what they are waiting on.
    </div>
  </div>
  <button type="button" class="btn btn-primary" style="font-size:12px;padding:8px 14px;white-space:nowrap"
          onclick="openMilestoneModal()">
    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New checklist
  </button>
</div>

@php
  // Which phase each column sits under, so a column and its band can be shown
  // or hidden together on a narrow screen.
  $phaseOf = [];
  $firstOfPhase = [];
  foreach (($grid?->phases ?? collect()) as $i => $phase) {
      foreach ($phase->keys as $k => $key) {
          $phaseOf[$key] = $i;
          if ($k === 0) {
              $firstOfPhase[$key] = true;
          }
      }
  }
@endphp

@if ($grid && $grid->rows->isNotEmpty())
  <div class="card" style="padding:0;overflow:hidden">

    {{-- Below the width where nineteen columns can breathe, one phase at a
         time. Same table; the other phases are simply not drawn. --}}
    <div class="ms-phases" id="ms-phases" @if ($grid->phases->count() < 2) style="display:none" @endif>
      @foreach ($grid->phases as $i => $phase)
        <button type="button" class="ms-ptab {{ $i === 0 ? 'on' : '' }}" data-ph="{{ $i }}">
          {{ $phase->name }}<span class="ms-pc">{{ $phase->span }}</span>
        </button>
      @endforeach
    </div>

    <div class="ms-scroll">
      <table class="ms-grid" id="ms-grid">
        <thead>
          {{-- Each header row carries its own Team cell. Hanging it off a
               rowspan meant hiding the band row on a narrow screen took the
               Team header with it and shifted every column one to the left. --}}
          <tr class="ms-phaserow">
            <th class="ms-team"></th>
            @foreach ($grid->phases as $i => $phase)
              <th class="ms-grp {{ $i % 2 ? 'ms-band' : '' }}" data-ph="{{ $i }}" colspan="{{ $phase->span }}">{{ $phase->name }}</th>
            @endforeach
          </tr>
          <tr class="ms-namerow">
            <th class="ms-team">
              <div class="ms-th">Team</div>
              <div class="ms-count">{{ $grid->rows->count() }} in your sections</div>
            </th>
            @forelse ($grid->columns as $col)
              @php $ph = $phaseOf[$col->key] ?? 0; @endphp
              @php
                $due = $col->due === 'mixed' ? null : $col->due;
                $dueLabel = $col->due === 'mixed'
                    ? 'mixed'
                    : ($due ? \Carbon\Carbon::parse($due)->format('M j') : 'set date');
              @endphp
              <th class="ms-set {{ $ph % 2 ? 'ms-band' : '' }} {{ isset($firstOfPhase[$col->key]) ? 'ms-grp' : '' }}"
                  data-ph="{{ $ph }}"
                  title="{{ $col->full }}{{ $col->due === 'mixed' ? ' — teams have different dates for this' : '' }}"
                  onclick="openDueModal(@js($col->key), @js($col->full), @js($due))">
                <div class="ms-lab">{{ $col->label }}</div>
                <span class="ms-kdot ms-k-{{ $col->kind }}"></span>
                <span class="ms-due {{ $col->due === 'mixed' ? 'mixed' : ($due ? '' : 'none') }}">{{ $dueLabel }}</span>
              </th>
            @empty
              <th class="ms-lab" style="text-align:left;padding-left:14px;font-weight:400">
                No checklist in {{ $selectedSection ?: 'these sections' }} yet &mdash; make one and every team gets it.
              </th>
            @endforelse
          </tr>
        </thead>

        <tbody>
          @php $lastSection = null; @endphp
          @foreach ($grid->rows as $row)
            @if ($row->section !== $lastSection)
              @php
                $lastSection = $row->section;
                $inSection = $grid->rows->where('section', $row->section);
                $secTotal = $inSection->sum('total');
                $secDone = $inSection->sum('completed');
              @endphp
              <tr class="ms-sec">
                <td class="ms-team">
                  {{ $row->section }}
                  <span class="ms-secmeta">{{ $inSection->count() }} {{ Str::plural('team', $inSection->count()) }}@if ($secTotal) &middot; {{ (int) round($secDone / $secTotal * 100) }}%@endif</span>
                </td>
                @forelse ($grid->columns as $col)
                  @php $ph = $phaseOf[$col->key] ?? 0; @endphp
                  <td data-ph="{{ $ph }}" class="{{ $ph % 2 ? 'ms-band' : '' }} {{ isset($firstOfPhase[$col->key]) ? 'ms-grp' : '' }}"></td>
                @empty
                  <td></td>
                @endforelse
              </tr>
            @endif

            <tr>
              <td class="ms-team">
                <div class="ms-tname">{{ $row->team->team_name }}</div>
                @if ($row->milestone)
                  <div class="ms-tprog">
                    <span class="ms-tbar"><i style="width:{{ $row->percent }}%"></i></span>
                    <span class="ms-count">{{ $row->completed }}/{{ $row->total }}</span>
                  </div>
                @endif
              </td>

              @if (! $row->milestone)
                {{-- Nineteen empty cells said nothing except "this row is
                     blank". One line says why, and offers the fix. --}}
                <td class="ms-none" colspan="{{ max(1, $grid->columns->count()) }}">No checklist yet</td>
              @else
              @foreach ($grid->columns as $col)
                @php
                  $ph = $phaseOf[$col->key] ?? 0;
                  $cell = $row->cells[$col->key] ?? null;
                  $done = $cell && in_array($cell->state, ['done', 'done_late'], true);
                @endphp
                <td class="ms-cell {{ $ph % 2 ? 'ms-band' : '' }} {{ isset($firstOfPhase[$col->key]) ? 'ms-grp' : '' }}"
                    data-ph="{{ $ph }}"
                    @if ($cell) title="{{ $col->full }} — {{ $row->team->team_name }}" @endif>
                  @if (! $cell)
                    {{-- This team's checklist does not carry that line at all,
                         which is not the same as having it and not doing it. --}}
                    <span class="ms-na">&middot;</span>
                  @elseif ($col->ticked_by_hand || ($cell->item->needs_check && ($cell->waiting_on === 'capsa' || $done)))
                    <button type="button"
                            class="ms-mk {{ $done ? 'on' : '' }} {{ $cell->state === 'overdue' ? 'over' : '' }} {{ $cell->waiting_on === 'capsa' ? 'wait' : '' }}"
                            onclick="toggleItem({{ $cell->item->id }}, {{ $done ? 'false' : 'true' }})"
                            title="{{ $done ? 'Mark as not checked' : ($cell->waiting_on === 'capsa' ? 'Mark as checked — open what they uploaded first' : 'Mark as checked') }}">
                      @include('capsa.partials.team-management.milestone-mark', ['state' => $cell->state])
                    </button>
                    @if ($cell->file_id)
                      <a href="{{ route('capsa.documents.file', $cell->file_id) }}" target="_blank" rel="noopener"
                         title="Open what {{ $row->team->team_name }} uploaded"
                         style="display:block;font-size:10px;font-weight:600;color:var(--accent3);margin-top:2px">view</a>
                    @endif
                  @else
                    {{-- Closes itself, so there is nothing to press. --}}
                    <span class="ms-mk auto {{ $done ? 'on' : '' }} {{ $cell->state === 'overdue' ? 'over' : '' }} {{ $cell->state === 'handed_in' ? 'wait' : '' }}"
                          title="{{ $cell->state === 'handed_in' ? match ($cell->waiting_on) { 'team' => 'Changes requested — the team is revising', 'capsa' => 'Waiting for you to check', null => 'In progress', default => 'With the '.$cell->waiting_on } : 'Closes on its own when the '.$col->kind.' happens' }}">
                      @include('capsa.partials.team-management.milestone-mark', ['state' => $cell->state])
                    </span>
                  @endif
                </td>
              @endforeach
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="ms-key">
      <span><span class="ms-mk on">@include('capsa.partials.team-management.milestone-mark', ['state' => 'done'])</span> Done</span>
      <span><span class="ms-mk wait"></span> With an adviser, consultant or panel</span>
      <span><span class="ms-mk over"></span> Overdue</span>
      <span><span class="ms-mk"></span> Not started</span>
      <span><span class="ms-mk auto"></span> Dashed &mdash; closes itself</span>
      <span class="ms-kinds">
        <span class="ms-kdot ms-k-form"></span>form
        <span class="ms-kdot ms-k-document"></span>document
        <span class="ms-kdot ms-k-approval"></span>approval
        <span class="ms-kdot ms-k-defense"></span>defense
      </span>
    </div>
  </div>
@else
  <div class="card"><div class="tm-empty">
    No teams in {{ $selectedSection ? 'section '.$selectedSection : 'your sections' }} yet.
  </div></div>
@endif

{{-- New checklist --}}
<div class="modal-overlay" id="ms-modal" onclick="if(event.target===this) closeMilestoneModal()">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-title">New checklist</div>
    <form onsubmit="createMilestone(event)">
      {{-- The form asks which section rather than reading the page filter.
           Clicking New checklist and being told to go back out and set a
           filter first is a dead end, and it is the one thing the form
           cannot do without. --}}
      <label class="ms-label">Section</label>
      <select class="input" name="section" id="ms-section">
        @forelse ($sectionChoices as $section => $count)
          <option value="{{ $section }}" @selected($section === $selectedSection) @disabled(! $count)>
            {{ $section }} &mdash; {{ $count ?: 'no' }} confirmed {{ Str::plural('team', $count) }}
          </option>
        @empty
          <option value="">You have no sections yet</option>
        @endforelse
      </select>

      <label class="ms-label">Checklist</label>
      <select class="input" name="template" id="ms-template" onchange="onTemplateChange()">
        @foreach ($templates as $key => $template)
          <option value="{{ $key }}">{{ $template['name'] }} &mdash; {{ count($template['items']) }} items</option>
        @endforeach
        <option value="">Empty &mdash; build it myself</option>
      </select>

      <div id="ms-title-wrap" style="display:none">
        <label class="ms-label" for="ms-title">Milestone Name</label>
        <input class="input" name="title" id="ms-title" placeholder="e.g. Capstone 2" maxlength="255"/>
      </div>

      {{-- Each line gets its own deadline from the grid header, because they
           fall weeks apart - the Chapter 1 draft long before the approval of
           it. This is only what a line falls back to until it is given one. --}}
      <label class="ms-label">Fallback due date <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
      <input class="input" type="date" name="due_date"/>
      <div class="ms-hint" style="margin-top:6px">
        Set each deliverable's own deadline afterwards by clicking its column heading.
      </div>

      {{-- Always the whole section. Every team in a section works from the
           same checklist, so handing them out one team at a time was work
           with no purpose. --}}
      <div class="ms-hint">
        Goes to every confirmed team in the section you picked. A team still forming
        does not get one until it is confirmed, and teams that already have this
        checklist keep whatever is ticked.
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeMilestoneModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" @disabled(! collect($sectionChoices)->filter()->count())>Create</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="ms-due-modal" onclick="if(event.target===this) closeDueModal()">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-title">Deadline</div>
    <form onsubmit="saveDue(event)">
      <div class="ms-hint" style="margin:0 0 12px">
        <strong id="ms-due-what"></strong> &mdash; due on this date for every team in
        <strong>{{ $selectedSection ?: 'the section' }}</strong>.
      </div>
      <label class="ms-label">Due date</label>
      <input class="input" type="date" id="ms-due-date"/>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeDueModal()">Cancel</button>
        <button type="button" class="btn btn-ghost" onclick="saveDue(event, true)">Clear</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const MS_SECTION = @json($selectedSection);
let MS_DUE_KEY = null;

/* A deliverable is due on the same day for the whole section, so the date is
   set once from its column heading rather than team by team. */
function openDueModal(key, full, due) {
  if (!MS_SECTION) {
    alert('Pick a single section above first - a deadline is set for one section at a time.');
    return;
  }
  MS_DUE_KEY = key;
  document.getElementById('ms-due-what').textContent = full;
  document.getElementById('ms-due-date').value = due || '';
  document.getElementById('ms-due-modal').classList.add('open');
}
function closeDueModal() { document.getElementById('ms-due-modal').classList.remove('open'); }

function saveDue(e, clear = false) {
  e.preventDefault();
  msPost('{{ route('capsa.milestones.deliverable_due') }}', {
    section: MS_SECTION,
    key: MS_DUE_KEY,
    due_date: clear ? null : (document.getElementById('ms-due-date').value || null),
  }).then(msReload).catch((err) => alert(err.message));
}


function msPost(url, body, method = 'POST') {
  return fetch(url, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
    body: body ? JSON.stringify(body) : null,
  }).then(async (r) => {
    const data = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(data.message || 'That did not work.');
    return data;
  });
}

// The page re-renders after a change rather than the browser guessing at the
// new state: a cell is worked out from documents, verdicts and outcomes
// recorded elsewhere, so a guess here would eventually disagree with the
// server.
function msReload() { window.location.reload(); }

function openMilestoneModal() {
  document.getElementById('ms-modal').classList.add('open');
}
function closeMilestoneModal() { document.getElementById('ms-modal').classList.remove('open'); }

function onTemplateChange() {
  const empty = document.getElementById('ms-template').value === '';
  document.getElementById('ms-title-wrap').style.display = empty ? 'block' : 'none';
  document.getElementById('ms-title').required = empty;
}

function createMilestone(e) {
  e.preventDefault();

  const form = new FormData(e.target);
  const section = form.get('section');

  if (!section) {
    alert('You have no sections to give a checklist to.');
    return;
  }

  msPost('{{ route('capsa.milestones.store') }}', {
    section,
    template: form.get('template') || null,
    title: form.get('title') || null,
    due_date: form.get('due_date') || null,
  }).then(msReload).catch((err) => alert(err.message));
}

function toggleItem(itemId, done) {
  msPost('/capsa/milestone-items/' + itemId + '/done', { done }, 'PUT')
    .then(msReload).catch((err) => alert(err.message));
}

// ── one phase at a time on a narrow screen ─────────────────────────────
(function () {
  const tabs = document.getElementById('ms-phases');
  const grid = document.getElementById('ms-grid');
  if (!tabs || !grid) return;

  const narrow = window.matchMedia('(max-width: 1279px)');
  let active = 0;

  function apply() {
    const on = narrow.matches;
    grid.querySelectorAll('[data-ph]').forEach((el) => {
      el.classList.toggle('ms-hide', on && Number(el.dataset.ph) !== active);
    });
    tabs.querySelectorAll('.ms-ptab').forEach((b) => {
      b.classList.toggle('on', Number(b.dataset.ph) === active);
    });
  }

  tabs.addEventListener('click', (e) => {
    const tab = e.target.closest('.ms-ptab');
    if (!tab) return;
    active = Number(tab.dataset.ph);
    apply();
  });

  // matchMedia's change event is not always delivered when a window is
  // dragged across the breakpoint, which left every column drawn in tab mode
  // until a tab was clicked.
  narrow.addEventListener('change', apply);
  window.addEventListener('resize', apply);
  apply();
})();
</script>
