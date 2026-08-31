{{-- Milestones: where a checklist is built AND where the papers get ticked.
     Deliberately one screen - a CaPSA working down a team's deliverables is
     doing both at once, and splitting them would mean two places to look. --}}

<div class="ms-intro">
  <div>
    <div class="ms-intro-title">Checklists</div>
    <div class="ms-intro-note">
      Only the <strong>signed forms</strong> are ticked here. A chapter draft closes when the team
      uploads it, a CLF when the adviser or consultant approves it, and a defense when the panel's
      decision is recorded &mdash; so those show what they are waiting on instead.
    </div>
  </div>
  <button type="button" class="btn btn-primary" style="font-size:12px;padding:8px 14px;white-space:nowrap"
          onclick="openMilestoneModal()">
    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    New checklist
  </button>
</div>

@forelse ($milestoneBoards as $board)
  <div class="card ms-team">
    <div class="ms-team-head">
      <div>
        <div class="ms-team-name">{{ $board->team->team_name }}</div>
        <div class="ms-team-sub">{{ $board->team->project_title ?: 'No title yet' }}</div>
      </div>
      @if ($board->milestones->isEmpty())
        <button type="button" class="btn btn-ghost" style="font-size:11px;padding:5px 12px"
                onclick="openMilestoneModal({{ $board->team->team_id }})">Give this team a checklist</button>
      @endif
    </div>

    @forelse ($board->milestones as $entry)
      @php $m = $entry->model; $p = $entry->progress; @endphp
      <div class="ms-block" id="ms-{{ $m->id }}">
        <div class="ms-block-head">
          <button type="button" class="ms-toggle" onclick="toggleMilestone({{ $m->id }})" aria-expanded="false">
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
          <div style="flex:1;min-width:0">
            <div class="ms-name">
              {{ $m->title }}
              @if ($m->due_date)
                <span class="ms-due">due {{ $m->due_date->format('j M Y') }}</span>
              @endif
            </div>
            <div class="ms-meta">
              <strong>{{ $p->completed }} of {{ $p->total }} complete</strong>
              @if ($p->handed_in) &middot; {{ $p->handed_in }} waiting @endif
              @if ($p->late) &middot; {{ $p->late }} late @endif
              @if ($p->overdue) &middot; <span class="ms-overdue">{{ $p->overdue }} overdue</span> @endif
            </div>
          </div>
          <div class="ms-bar" title="{{ $p->percent }}%">
            <div class="ms-bar-fill" style="width:{{ $p->percent }}%"></div>
          </div>
          <button type="button" class="ms-del" title="Delete this checklist" onclick="deleteMilestone({{ $m->id }}, '{{ addslashes($m->title) }}')">
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>
        </div>

        <div class="ms-items">
          @forelse ($p->items as $row)
            @php $i = $row->item; @endphp
            <div class="ms-item ms-{{ $row->state }}">
              @if ($i->isTickedByHand())
                <button type="button" class="ms-tick {{ in_array($row->state, ['done', 'done_late'], true) ? 'on' : '' }}"
                        onclick="toggleItem({{ $i->id }}, {{ in_array($row->state, ['done', 'done_late'], true) ? 'false' : 'true' }})"
                        title="{{ in_array($row->state, ['done', 'done_late'], true) ? 'Mark as not done' : 'Mark as sighted' }}">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
              @else
                {{-- Closes itself, so there is nothing to press. --}}
                <span class="ms-tick auto {{ in_array($row->state, ['done', 'done_late'], true) ? 'on' : '' }}"
                      title="Closes on its own when the {{ $i->kind }} happens">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
              @endif

              <div class="ms-item-name">
                {{ $i->title }}
                @if ($row->state === 'done_late')
                  <span class="ms-flag">{{ $row->days_late }} {{ Str::plural('day', $row->days_late) }} late</span>
                @endif
              </div>

              <span class="ms-kind ms-kind-{{ $i->kind }}">{{ $i->kind }}</span>

              <span class="ms-when">
                @if ($row->closed_at)
                  {{ $row->closed_at->format('j M') }}
                @elseif ($row->state === 'handed_in')
                  <span class="ms-waiting">{{ $row->waiting_on ? 'with the '.$row->waiting_on : 'in progress' }}</span>
                @elseif ($row->state === 'overdue')
                  <span class="ms-overdue">overdue</span>
                @else
                  &mdash;
                @endif
              </span>

              @if ($i->template_key === null)
                <button type="button" class="ms-del" title="Remove this line" onclick="deleteItem({{ $i->id }})">
                  <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
              @else
                <span style="width:22px"></span>
              @endif
            </div>
          @empty
            <div class="ms-empty">
              Nothing on this checklist yet.
              <button type="button" class="ms-link" onclick="applyTemplate({{ $m->id }})">Use the Capstone 1 checklist</button>
            </div>
          @endforelse

          <form class="ms-add" onsubmit="addItem(event, {{ $m->id }})">
            <input class="input" name="title" placeholder="Add a line of your own&hellip;" maxlength="255" required/>
            <button type="submit" class="btn btn-ghost" style="font-size:11px;padding:6px 12px">Add</button>
          </form>
        </div>
      </div>
    @empty
      <div class="ms-none">No checklist yet.</div>
    @endforelse
  </div>
@empty
  <div class="card"><div class="tm-empty">
    No teams in {{ $selectedSection ? 'section '.$selectedSection : 'your sections' }} yet.
  </div></div>
@endforelse

{{-- New checklist --}}
<div class="modal-overlay" id="ms-modal" onclick="if(event.target===this) closeMilestoneModal()">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-title">New checklist</div>
    <form onsubmit="createMilestone(event)">
      <input type="hidden" name="team_id" id="ms-team-id"/>

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

      <label class="ms-label">Give it to</label>
      <select class="input" name="scope" id="ms-scope">
        <option value="team">This team only</option>
        <option value="section">Every team in {{ $selectedSection ?: 'the section' }}</option>
      </select>
      @unless ($selectedSection)
        <div class="ms-hint">Pick a section above to give a checklist to every team at once.</div>
      @endunless

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="closeMilestoneModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Create</button>
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

// The whole tab re-renders after a change rather than patching rows by hand:
// an item's state is worked out from records elsewhere, so guessing at the
// new state in the browser would eventually disagree with the server.
function msReload() { window.location.reload(); }

function openMilestoneModal(teamId) {
  document.getElementById('ms-team-id').value = teamId || '';
  document.getElementById('ms-scope').value = teamId ? 'team' : (MS_SECTION ? 'section' : 'team');
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
  const scope = form.get('scope');
  const teamId = form.get('team_id');

  if (scope === 'team' && !teamId) {
    alert('Use the button on a team to give that team a checklist.');
    return;
  }
  if (scope === 'section' && !MS_SECTION) {
    alert('Pick a section above first.');
    return;
  }

  msPost('{{ route('admin.milestones.store') }}', {
    scope,
    team_id: teamId || null,
    section: MS_SECTION,
    template: form.get('template') || null,
    title: form.get('title') || null,
    due_date: form.get('due_date') || null,
  }).then(msReload).catch((err) => alert(err.message));
}

function toggleMilestone(id) {
  document.getElementById('ms-' + id).classList.toggle('open');
}

function toggleItem(itemId, done) {
  msPost('/admin/milestone-items/' + itemId + '/toggle', { done }).then(msReload).catch((err) => alert(err.message));
}

function addItem(e, milestoneId) {
  e.preventDefault();
  const title = new FormData(e.target).get('title');
  msPost('/admin/milestones/' + milestoneId + '/items', { title }).then(msReload).catch((err) => alert(err.message));
}

function deleteItem(itemId) {
  msPost('/admin/milestone-items/' + itemId, null, 'DELETE').then(msReload).catch((err) => alert(err.message));
}

function applyTemplate(milestoneId) {
  msPost('/admin/milestones/' + milestoneId + '/template', { template: 'capstone_1' })
    .then(msReload).catch((err) => alert(err.message));
}

function deleteMilestone(id, title) {
  if (!confirm('Delete "' + title + '"? Everything ticked on it goes too.')) return;
  msPost('/admin/milestones/' + id, null, 'DELETE').then(msReload).catch((err) => alert(err.message));
}
</script>
