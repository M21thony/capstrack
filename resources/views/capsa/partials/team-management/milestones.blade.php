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
      Only the <strong>forms</strong> are ticked here. A draft closes when the team uploads it, a CLF
      when the adviser or consultant approves it, and a defense when the panel's decision is
      recorded &mdash; those are drawn dashed and say what they are waiting on.
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
              <th class="{{ $ph % 2 ? 'ms-band' : '' }} {{ isset($firstOfPhase[$col->key]) ? 'ms-grp' : '' }}"
                  data-ph="{{ $ph }}" title="{{ $col->full }}">
                <div class="ms-lab">{{ $col->label }}</div>
                <span class="ms-kdot ms-k-{{ $col->kind }}"></span>
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
                  @elseif ($col->ticked_by_hand)
                    <button type="button"
                            class="ms-mk {{ $done ? 'on' : '' }} {{ $cell->state === 'overdue' ? 'over' : '' }}"
                            onclick="toggleItem({{ $cell->item->id }}, {{ $done ? 'false' : 'true' }})"
                            title="{{ $done ? 'Mark as not sighted' : 'Mark as sighted' }}">
                      @include('capsa.partials.team-management.milestone-mark', ['state' => $cell->state])
                    </button>
                  @else
                    {{-- Closes itself, so there is nothing to press. --}}
                    <span class="ms-mk auto {{ $done ? 'on' : '' }} {{ $cell->state === 'overdue' ? 'over' : '' }} {{ $cell->state === 'handed_in' ? 'wait' : '' }}"
                          title="{{ $cell->state === 'handed_in' ? ($cell->waiting_on ? 'With the '.$cell->waiting_on : 'In progress') : 'Closes on its own when the '.$col->kind.' happens' }}">
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
      <label class="ms-label">Checklist</label>
      <select class="input" name="template" id="ms-template" onchange="onTemplateChange()">
        @foreach ($templates as $key => $template)
          <option value="{{ $key }}">{{ $template['name'] }} &mdash; {{ count($template['items']) }} items</option>
        @endforeach
        <option value="">Empty &mdash; build it myself</option>
      </select>

      <div id="ms-title-wrap" style="display:none">
        <label class="ms-label">Name</label>
        <input class="input" name="title" id="ms-title" placeholder="e.g. Capstone 2" maxlength="255"/>
      </div>

      <label class="ms-label">Due date <span style="font-weight:400;color:var(--text3)">(optional)</span></label>
      <input class="input" type="date" name="due_date"/>

      {{-- Always the whole section. Every team in a section works from the
           same checklist, so handing them out one team at a time was work
           with no purpose. --}}
      <div class="ms-hint">
        @if ($selectedSection && $sectionTeamCount)
          Goes to the <strong>{{ $sectionTeamCount }} confirmed {{ Str::plural('team', $sectionTeamCount) }}</strong>
          in <strong>{{ $selectedSection }}</strong>. A team still forming does not get one until it is
          confirmed. Teams that already have this checklist keep whatever is ticked.
        @elseif ($selectedSection)
          No confirmed team in <strong>{{ $selectedSection }}</strong> yet, so there is nobody to give
          this to. A team gets its checklist once it is confirmed.
        @else
          Choose a section above first &mdash; a checklist goes to the whole section.
        @endif
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeMilestoneModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" @disabled(! $selectedSection || ! $sectionTeamCount)>Create</button>
      </div>
    </form>
  </div>
</div>

<script>
const MS_SECTION = @json($selectedSection);

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
  document.getElementById('ms-modal').classList.add('show');
}
function closeMilestoneModal() { document.getElementById('ms-modal').classList.remove('show'); }

function onTemplateChange() {
  const empty = document.getElementById('ms-template').value === '';
  document.getElementById('ms-title-wrap').style.display = empty ? 'block' : 'none';
  document.getElementById('ms-title').required = empty;
}

function createMilestone(e) {
  e.preventDefault();

  if (!MS_SECTION) {
    alert('Choose a section above first.');
    return;
  }

  const form = new FormData(e.target);

  msPost('{{ route('admin.milestones.store') }}', {
    section: MS_SECTION,
    template: form.get('template') || null,
    title: form.get('title') || null,
    due_date: form.get('due_date') || null,
  }).then(msReload).catch((err) => alert(err.message));
}

function toggleItem(itemId, done) {
  msPost('/admin/milestone-items/' + itemId + '/toggle', { done })
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
