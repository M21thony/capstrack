@php $expert = auth('expert')->user(); @endphp

@once
<style>
  /* Scoped copy of the "EXPERT PROFILE SIDE PANEL" rules from
     public/css/expert.css. Instructor pages already load that whole
     stylesheet, but this partial is also included from the merged
     CaPSA/Admin sidebar (capsa/_sidebar.blade.php), which only loads
     admin.css - loading all of expert.css there would collide with
     admin.css's own .sidebar/.nav/.btn/.card rules. Both stylesheets
     share the same CSS variables (--navy-dark, --accent, --border,
     etc.), so this scoped block renders identically in both places. */
  .ep-overlay{position:fixed;inset:0;background:transparent;z-index:200;display:none;pointer-events:none}
  .ep-overlay.open{display:block}
  /* Scrolls without a bar. The panel is 300px wide and the bar took a slice
     of that off every row of chips, on a surface that is already obviously
     scrollable from its content running past the bottom. */
  .ep-modal{position:fixed;top:0;left:250px;width:300px;height:100vh;height:100dvh;background:#fff;box-shadow:4px 0 24px rgba(0,0,0,0.18);overflow-y:auto;pointer-events:all;transform:translateX(-10px);opacity:0;transition:transform 0.18s ease,opacity 0.18s ease;z-index:201;border-right:1px solid var(--border);scrollbar-width:none;-ms-overflow-style:none}
  .ep-modal::-webkit-scrollbar{display:none}
  .ep-overlay.open .ep-modal{transform:translateX(0);opacity:1}
  /* Sits over the body now that there is no bar to sit in. Kept at the top
     because a panel this tall would otherwise only be closable from a button
     at the bottom of a scroll. */
  /* Sticky, so the way out and the name of the panel stay put through a
     scroll that is most of this panel's height. */
  /* The dark bar with the gold rule under it, the way the sidebar beside it
     is dressed - it just no longer carries a name and a face. */
  .ep-header{position:sticky;top:0;z-index:3;display:flex;align-items:center;gap:10px;
             padding:13px 16px;background:var(--navy-dark);border-bottom:2px solid var(--accent)}
  .ep-header-title{flex:1;min-width:0;font-size:13px;font-weight:700;color:var(--navy-text);
                   letter-spacing:-0.1px}
  .ep-close{width:26px;height:26px;flex-shrink:0;
            background:transparent;border:none;border-radius:7px;cursor:pointer;
            color:var(--navy-text2);padding:0;display:flex;align-items:center;justify-content:center;
            transition:color 0.13s,background 0.13s}
  .ep-close:hover{color:var(--navy-text);background:rgba(255,255,255,0.08)}
  .ep-close svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2.5}
  .ep-body{padding:18px}
  .ep-field{margin-bottom:15px}
  .ep-field:last-child{margin-bottom:0}
  .ep-label{font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.9px;margin-bottom:6px}
  .ep-value{font-size:13px;font-weight:500;color:var(--text);background:var(--bg3);border:1.5px solid var(--border);border-radius:8px;padding:8px 11px}
  .ep-value.readonly{color:var(--text2);background:var(--bg4);cursor:not-allowed;display:flex;align-items:center;gap:7px}
  .ep-value.readonly svg{width:12px;height:12px;stroke:var(--text3);fill:none;stroke-width:2;flex-shrink:0}
  .ep-value.ep-editable{display:flex;align-items:center;gap:7px}
  .ep-value.ep-editable svg{width:12px;height:12px;stroke:var(--text3);fill:none;stroke-width:2;flex-shrink:0}
  .ep-number-input{width:40px;border:1.5px solid var(--border);background:#fff;border-radius:6px;padding:3px 4px;font-family:inherit;font-size:13px;font-weight:700;color:var(--text);text-align:center;outline:none;transition:border 0.15s}
  .ep-number-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,168,44,0.1)}
  .ep-programs{display:flex;flex-direction:column;gap:6px}
  .ep-program-row{
    display:flex;align-items:center;gap:8px;padding:7px 10px;cursor:pointer;
    border:1.5px solid var(--border);border-radius:8px;background:#fff;transition:all .12s;
  }
  .ep-program-row:hover{border-color:rgba(201,168,44,0.45)}
  .ep-program-row.on{border-color:var(--accent);background:rgba(201,168,44,0.07)}
  .ep-program-check{width:14px;height:14px;accent-color:var(--accent);flex-shrink:0}
  .ep-program-name{font-size:12.5px;font-weight:700;color:var(--text);min-width:44px}
  .ep-program-slots{
    margin-left:auto;width:46px;border:1.5px solid var(--border);background:#fff;border-radius:6px;
    padding:3px 4px;font-family:inherit;font-size:12.5px;font-weight:700;color:var(--text);
    text-align:center;outline:none;transition:border .15s;
  }
  .ep-program-slots:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,168,44,0.1)}
  .ep-program-slots:disabled{background:var(--bg4);color:var(--text3);cursor:not-allowed}
  .ep-program-unit{font-size:11px;color:var(--text3);min-width:34px}
  .ep-divider{height:1px;background:var(--border);margin:14px 0}
  .ep-roles{display:flex;flex-wrap:wrap;gap:5px}
  .ep-tags-wrap{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:8px;min-height:26px}
  .ep-tag-input-row{display:flex;align-items:center;gap:8px;padding:7px 10px;border:1.5px solid var(--border);border-radius:9px;background:#fff}
  .ep-tag-input-row:focus-within{border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,168,44,0.1)}
  .ep-tag-input{flex:1;min-width:0;border:none;outline:none;background:transparent;font-family:'DM Sans','DM Sans Fallback',sans-serif;font-size:12.5px;color:var(--text)}
  .ep-tag-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,168,44,0.1)}
  .ep-tag-input::placeholder{color:var(--text3)}
  .ep-quick-groups{margin-top:10px}
  /* The strip that replaces scrolling: every group reachable in one click,
     and only the one being looked at taking up room. */
  .ep-grp-tabs{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:9px;
               padding-bottom:8px;border-bottom:1px solid var(--border)}
  .ep-grp-tab{font-family:'DM Sans','DM Sans Fallback',sans-serif;font-size:10.5px;font-weight:700;
              letter-spacing:0.3px;padding:4px 9px;border-radius:7px;border:1px solid transparent;
              background:transparent;color:var(--text3);cursor:pointer;
              display:inline-flex;align-items:center;gap:5px;transition:background .12s,color .12s}
  .ep-grp-tab:hover{background:var(--bg4);color:var(--text2)}
  .ep-grp-tab.on{background:var(--bg4);color:var(--text);border-color:var(--border2)}
  /* Not a group of the catalogue - what they hold out of all of them. */
  .ep-grp-tab-selected{color:var(--accent3)}
  .ep-grp-tab-selected.on{background:var(--accent-light);border-color:var(--accent)}
  /* How many of this group they hold - the reason to look in it, or not. */
  .ep-grp-tab-n{font-size:9.5px;font-weight:700;padding:0 5px;border-radius:999px;
                background:var(--blue-bg);color:var(--blue)}
  .ep-grp-tab.on .ep-grp-tab-n{background:var(--accent);color:#18191f}
  /* A tab of a dozen chips never reaches this. Somebody holding thirty
     skills would otherwise push Save off the bottom of the panel, so the
     chips stop growing and scroll instead - without a bar, because the panel
     already has one and two side by side reads as a fault. */
  .ep-quick-chips{display:flex;flex-wrap:wrap;gap:5px;max-height:196px;overflow-y:auto;
                  scrollbar-width:none;-ms-overflow-style:none}
  .ep-quick-chips::-webkit-scrollbar{display:none}
  .ep-picker-hint{font-size:11px;color:var(--text3);margin-top:9px;line-height:1.45}
  .ep-picker-empty{font-size:11.5px;color:var(--text3);padding:14px 12px;text-align:center;
                   border:1px dashed var(--border);border-radius:9px}
  .ep-search-ico{width:14px;height:14px;flex-shrink:0;stroke:var(--text3);fill:none;stroke-width:2}
  .ep-search-clear{width:20px;height:20px;flex-shrink:0;border:none;background:transparent;
                   cursor:pointer;color:var(--text3);align-items:center;justify-content:center;
                   border-radius:5px;padding:0}
  .ep-search-clear svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2.4}
  .ep-search-clear:hover{background:var(--bg4);color:var(--text)}
  .ep-quick-chip{
    font-family:'DM Sans','DM Sans Fallback',sans-serif;font-size:11px;font-weight:600;padding:4px 10px;
    border-radius:999px;border:1px solid var(--border);background:#fff;color:var(--text2);
    cursor:pointer;transition:border-color .12s,background .12s,color .12s;
    max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
  }
  .ep-quick-chip:hover{border-color:var(--accent);background:var(--accent-light);color:var(--text)}
  /* Taken. The same chip gives it back, so there is no second list of little
     x buttons to hunt through to change your mind. */
  .ep-quick-chip.picked{border-color:var(--accent);background:var(--accent);color:#18191f}
  .ep-quick-chip.picked:hover{background:var(--accent2);border-color:var(--accent2)}
  .ep-footer{padding:12px 18px 16px;border-top:1px solid var(--border);display:flex;gap:8px;position:sticky;bottom:0;background:#fff;z-index:1}
  .ep-footer .btn{flex:1;justify-content:center;font-size:12px;padding:7px 10px}
  .role-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:22px;font-size:11px;font-weight:700;letter-spacing:0.3px;text-transform:uppercase;background:var(--bg4);color:var(--text2);border:1px solid var(--border)}
  .role-pill svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5;flex-shrink:0}
  .role-adviser{background:rgba(201,168,44,0.12);color:var(--accent3);border:1px solid rgba(201,168,44,0.3)}
  .role-capstone_adviser{background:var(--blue-bg);color:var(--blue);border:1px solid rgba(29,78,216,0.2)}
  .role-consultant{background:var(--cyan-bg);color:var(--cyan);border:1px solid rgba(8,145,178,0.2)}
  .role-grammarian{background:var(--green-bg);color:var(--green);border:1px solid rgba(22,163,74,0.2)}
</style>
@endonce

<!-- EXPERT PROFILE SIDE PANEL -->
<div class="ep-overlay" id="ep-overlay" onclick="handleEpOverlay(event)">
  <div class="ep-modal" id="ep-modal">

    {{-- Says what the panel is, not whose it is: the sidebar a few inches to
         the left is already showing the name and the face, and repeating them
         cost the first 80px of something that has to scroll. --}}
    <div class="ep-header">
      <span class="ep-header-title">Expert Profile</span>
      <button class="ep-close" onclick="closeExpertProfile()" aria-label="Close">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Scrollable Body -->
    <div class="ep-body">

      <!-- Full Name -->
      <div class="ep-field">
        <div class="ep-label">Full Name</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          {{ $expert?->first_name }} {{ $expert?->last_name }}@if($expert?->credentials), {{ $expert?->credentials }}@endif
        </div>
      </div>

      <!-- Email -->
      <div class="ep-field">
        <div class="ep-label">Email Address</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          {{ $expert?->email }}
        </div>
      </div>

      <div class="ep-divider"></div>

        <!-- Roles -->
        <div class="ep-field">
          <div class="ep-label">Assigned Roles</div>
          <div class="ep-tags-wrap">
            @foreach($expert?->roles ?? [] as $role)
              <span class="role-pill role-{{ $role->role_name }}">{{ $role->label }}</span>
            @endforeach
          </div>
        <div style="font-size:11px;color:var(--text3);margin-top:8px;display:flex;align-items:center;gap:4px">
          <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Managed by the system administrator
        </div>
      </div>

      <div class="ep-divider"></div>

      <!-- Highest degree: what the Panel Chair rule reads -->
      <div class="ep-field">
        <label class="ep-label" for="ep-degree">Degree</label>
        @include('partials.degree-select', [
            'id' => 'ep-degree',
            'selected' => $expert?->degree_title,
            'attrs' => 'style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--bg2);color:var(--text);font:inherit;font-size:13px"',
        ])
        <div style="font-size:11px;color:var(--text3);margin-top:6px">A Panel Chair needs a Master's degree or higher.</div>
      </div>

      <div class="ep-divider"></div>

      <!-- Programs Accepted + per-program team slots -->
      @php
        // Prefer what they last saved (parsed back out of program_slots);
        // fall back to their flat max_teams against their own program so an
        // expert who has never opened this panel still sees something sane.
        $savedSlots = [];
        foreach ($expert?->parsedCapacity() ?? [] as [$prog, $max]) {
            if ($prog !== 'ALL') {
                $savedSlots[$prog] = $max;
            }
        }
      @endphp
      <div class="ep-field">
        <div class="ep-label">Programs Accepted</div>
        <div class="ep-programs">
          @foreach (\App\Models\CapstoneExpert::PROGRAMS as $program)
            @php $slots = $savedSlots[$program] ?? 0; @endphp
            <label class="ep-program-row {{ $slots > 0 ? 'on' : '' }}" data-program="{{ $program }}">
              <input type="checkbox" class="ep-program-check" {{ $slots > 0 ? 'checked' : '' }} onchange="epToggleProgram(this)">
              <span class="ep-program-name">{{ $program }}</span>
              <input type="number" class="ep-program-slots" min="1" max="20"
                     value="{{ $slots > 0 ? $slots : 1 }}" {{ $slots > 0 ? '' : 'disabled' }}
                     oninput="epUpdateSlotTotal()">
              <span class="ep-program-unit">teams</span>
            </label>
          @endforeach
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:7px">
          Tick each program you take teams from and set how many. You can take a
          different number per program - e.g. 1 BSIT team and 5 BSIS teams.
          <span id="ep-slot-total" style="display:block;margin-top:3px;font-weight:600;color:var(--text2)"></span>
        </div>
      </div>

      <div class="ep-divider"></div>

      @if($expert?->program)
      <div class="ep-field">
        <div class="ep-label">Program</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><path d="M21 10H3V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5zM3 10h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V10"/></svg>
          {{ $expert?->program }}
        </div>
      </div>
      <div class="ep-divider"></div>
      @endif

      <!-- Expertise Tags -->
      <div class="ep-field">
        <div class="ep-label">Areas of Expertise</div>
        @php
          $expertiseList = \App\Models\ExpertExpertise::where('expert_id', $expert?->expert_id)->pluck('expertise_field')->toArray();
        @endphp
        {{-- No separate list of what has been taken. In a panel this narrow a
             long skill name is a row of its own, so fifteen of them pushed the
             thing you pick with off the bottom of the screen. They live in the
             picker instead, as a Selected tab beside the others. --}}
        {{-- One box and one list. Picking used to mean choosing between a
             dropdown, an "+ Add" button and an accordion you opened a group
             at a time - three ways to do one thing, and none of them showing
             you what you had already taken. --}}
        <div class="ep-tag-input-row">
          <svg class="ep-search-ico" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
          <input class="ep-tag-input" id="ep-tag-input" type="text" autocomplete="off"
                 placeholder="Type to narrow the list below"
                 onkeydown="handleTagKey(event)" oninput="epRenderQuickGroups()"/>
          <button type="button" class="ep-search-clear" id="ep-search-clear" onclick="epClearSearch()" title="Clear" style="display:none">
            <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>

        <div class="ep-quick-groups" id="ep-quick-groups"></div>

        <div class="ep-picker-hint">
          Click to take one, click again to drop it. Anything not on the list &mdash;
          type it and press Enter.
        </div>
      </div>

    </div>

    <!-- Sticky Footer -->
    <div class="ep-footer" style="flex-direction:column;align-items:stretch;gap:8px">
      <div id="ep-save-msg" style="display:none;font-size:11.5px;border-radius:8px;padding:8px 11px"></div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-ghost" style="color:var(--text2);border-color:var(--border);background:transparent" onclick="closeExpertProfile()">Close</button>
        <button class="btn btn-primary" id="ep-save-btn" onclick="saveExpertProfile()">
          <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          Save
        </button>
      </div>
    </div>

  </div>
</div>

<script>
const EP_OVERLAY = document.getElementById('ep-overlay');
const EP_TRIGGER = document.getElementById('ep-trigger');
const EP_INPUT   = document.getElementById('ep-tag-input');

let epTags = @json($expertiseList ?? []);

// Suggested skills, grouped by the kind of work they belong to. This is a
// starting point, not a whitelist - anything typed that isn't here is still
// accepted. Free text alone meant every expert invented their own wording
// ("UI/UX", "ui ux design", "User Interface"), which made the lists students
// see impossible to scan or match on.
// Sourced from App\Support\SkillCatalog so the suggestions offered here and
// the groups the student expert list filters by can never drift apart.
const EP_ALL_GROUPS = Object.entries(@json(\App\Support\SkillCatalog::groups()))
  .map(([name, items]) => ({ name, items }));

// CaPSA asked that each programme have its own areas of expertise: the four
// are not the same subject, and offering a BLIS adviser "Embedded Systems"
// made the picker longer without making it more useful. Ticking a programme
// above narrows what is suggested here; ticking none shows everything, because
// narrowing to nothing would leave an expert with no technical skills at all.
const EP_PROGRAM_SKILLS = @json(\App\Support\SkillCatalog::programSkills());
const EP_SHARED_GROUPS = @json(\App\Support\SkillCatalog::sharedGroups());

// Every skill the catalogue knows, whichever programme it belongs to. What is
// SUGGESTED narrows by programme; what is RECOGNISED must not, or a skill
// typed from another programme would be treated as something nobody has heard
// of - and an expert is free to type any of them.
const EP_ALL_SKILLS = EP_ALL_GROUPS.flatMap(g => g.items.map(i => ({ name: i, group: g.name })));

/** The programmes ticked in Programs Accepted, right now. */
function epCheckedPrograms(){
  return [...document.querySelectorAll('.ep-program-row')]
    .filter(row => row.querySelector('.ep-program-check')?.checked)
    .map(row => row.dataset.program);
}

/** The catalogue as this expert's programmes see it. */
function epSkillGroups(){
  const codes = epCheckedPrograms().filter(c => EP_PROGRAM_SKILLS[c]);

  if (!codes.length) return EP_ALL_GROUPS;

  const allowed = new Set();
  codes.forEach(c => EP_PROGRAM_SKILLS[c].forEach(s => allowed.add(epNorm(s))));

  return EP_ALL_GROUPS
    // Research craft and language belong to every programme - a capstone in
    // any of them needs a literature review and a proofread.
    .map(g => EP_SHARED_GROUPS.includes(g.name)
      ? g
      // Something already taken stays on show even when it belongs to
      // another programme. Hiding it would leave a skill they hold with no
      // tick beside it anywhere in this list, which reads as not having it.
      : { name: g.name, items: g.items.filter(i => allowed.has(epNorm(i)) || epHasTag(i)) })
    .filter(g => g.items.length);
}

function epNorm(v){ return String(v).trim().toLowerCase(); }
function epHasTag(name){ return epTags.some(t => epNorm(t) === epNorm(name)); }

// Long enough to scroll is long enough to be a chore, and the list was that
// whether it was folded into rows to open or laid out flat. One group is
// shown at a time, picked from a strip along the top: a dozen or so chips,
// all of them on screen, and switching group is a click rather than a scroll.
const EP_SHORT_LABELS = {
  'Research & Writing (Adviser)': 'Research',
  'Language & Editing (Grammarian)': 'Language',
  'Software & Development': 'Software',
  'Data, AI & Systems': 'Data & AI',
  'Information & Library Science': 'Info Science',
  'Your own': 'Yours',
};

const EP_SELECTED = 'Selected';

let epActiveGroup = null;

function epShortLabel(name){ return EP_SHORT_LABELS[name] || name; }

/** The groups worth showing: filtered by programme, then by what was typed. */
function epVisibleGroups(){
  const q = epNorm(document.getElementById('ep-tag-input')?.value || '');

  const groups = epSkillGroups()
    .map(g => ({ name: g.name, items: g.items.filter(i => !q || epNorm(i).includes(q)) }))
    .filter(g => g.items.length);

  // Skills they typed themselves are theirs to see and drop too, so they get
  // a group rather than disappearing.
  const known = new Set(EP_ALL_SKILLS.map(s => epNorm(s.name)));
  const own = epTags.filter(t => !known.has(epNorm(t)) && (!q || epNorm(t).includes(q)));

  if (own.length) groups.push({ name: 'Your own', items: own });

  // Everything taken, wherever it came from, in one tab. It goes first
  // because "what do I already have" is the question somebody opening this
  // panel has, and it is the only view that answers it in one place.
  const taken = epTags.filter(t => !q || epNorm(t).includes(q));

  if (taken.length) groups.unshift({ name: EP_SELECTED, items: taken });

  return groups;
}

function epSelectGroup(name){
  epActiveGroup = name;
  epRenderQuickGroups();
}

function epRenderQuickGroups(){
  const wrap = document.getElementById('ep-quick-groups');
  if (!wrap) return;

  const raw = document.getElementById('ep-tag-input')?.value || '';
  const clear = document.getElementById('ep-search-clear');
  if (clear) clear.style.display = raw.trim() ? 'flex' : 'none';

  const groups = epVisibleGroups();

  if (!groups.length) {
    wrap.innerHTML = '<div class="ep-picker-empty">Nothing matches &ldquo;'
      + epEscape(raw.trim()) + '&rdquo;. Press Enter to add it anyway.</div>';

    return;
  }

  // Searching crosses every group, so the tabs would be answering a question
  // nobody asked - show the matches together instead.
  const searching = raw.trim() !== '';

  const chipsFor = items => items.map(function (i) {
    const picked = epHasTag(i);

    // One control per skill: the same chip takes it and gives it back.
    return '<button type="button" class="ep-quick-chip' + (picked ? ' picked' : '') + '"'
         + ' onclick="epToggleSkill(' + JSON.stringify(i).replace(/"/g, '&quot;') + ')"'
         + ' title="' + (picked ? 'Remove' : 'Add') + '">'
         + (picked ? '&#10003; ' : '') + epEscape(i) + '</button>';
  }).join('');

  if (searching) {
    wrap.innerHTML = '<div class="ep-quick-chips">'
      + chipsFor(groups.flatMap(g => g.items)) + '</div>';

    return;
  }

  // Keep whatever they were looking at, unless the programmes changed under
  // them and it is not there any more.
  if (!groups.some(g => g.name === epActiveGroup)) {
    epActiveGroup = groups[0].name;
  }

  const tabs = groups.map(function (g) {
    const taken = g.items.filter(epHasTag).length;
    const on = g.name === epActiveGroup;

    const kind = g.name === EP_SELECTED ? ' ep-grp-tab-selected' : '';

    return '<button type="button" class="ep-grp-tab' + kind + (on ? ' on' : '') + '"'
         + ' onclick="epSelectGroup(' + JSON.stringify(g.name).replace(/"/g, '&quot;') + ')">'
         + epEscape(epShortLabel(g.name))
         + (taken ? '<span class="ep-grp-tab-n">' + taken + '</span>' : '')
         + '</button>';
  }).join('');

  const active = groups.find(g => g.name === epActiveGroup);

  wrap.innerHTML = '<div class="ep-grp-tabs">' + tabs + '</div>'
    + '<div class="ep-quick-chips">' + chipsFor(active.items) + '</div>';
}

/** Text from a person, put on the page as text and never as markup. */
function epEscape(value){
  const box = document.createElement('div');
  box.textContent = value == null ? '' : String(value);
  return box.innerHTML;
}

/** The one thing a chip does: take this skill, or give it back. */
function epToggleSkill(name){
  const at = epTags.findIndex(t => epNorm(t) === epNorm(name));

  at >= 0 ? epTags.splice(at, 1) : epTags.push(name);

  renderTags();
  document.getElementById('ep-tag-input')?.focus();
}

function epClearSearch(){
  const input = document.getElementById('ep-tag-input');
  input.value = '';
  epRenderQuickGroups();
  input.focus();
}

function renderTags(){
  // The picker is the only place skills are shown now, so keeping it in step
  // is the whole of this. Everything that adds or removes one comes through
  // here, whichever route it took.
  epRenderQuickGroups();
}

function addTag(){
  const val = EP_INPUT.value.trim();
  if(!val) return;
  if(epHasTag(val)){ EP_INPUT.select(); return; }

  // If what they typed matches a suggested skill apart from casing, store
  // the suggested spelling - that is the whole point of having the list.
  const known = EP_ALL_SKILLS.find(s => epNorm(s.name) === epNorm(val));
  epTags.push(known ? known.name : val);

  EP_INPUT.value = '';
  renderTags();
  EP_INPUT.focus();
}

function handleTagKey(e){ if(e.key==='Enter'){ e.preventDefault(); addTag(); } }

function epToggleProgram(cb){
  var row = cb.closest('.ep-program-row');
  var num = row.querySelector('.ep-program-slots');
  row.classList.toggle('on', cb.checked);
  num.disabled = !cb.checked;
  if (cb.checked && (!num.value || Number(num.value) < 1)) num.value = 1;
  epUpdateSlotTotal();
  // What they take teams from decides what they are offered to be expert in.
  epRenderQuickGroups();
}

function epUpdateSlotTotal(){
  var total = 0, parts = [];
  document.querySelectorAll('.ep-program-row').forEach(function (row) {
    if (!row.querySelector('.ep-program-check').checked) return;
    var n = Number(row.querySelector('.ep-program-slots').value) || 0;
    if (n > 0) { total += n; parts.push(n + ' ' + row.dataset.program); }
  });
  var el = document.getElementById('ep-slot-total');
  if (el) el.textContent = total ? 'Total: ' + total + ' teams (' + parts.join(', ') + ')' : 'No programs selected yet.';
}

function openExpertProfile(){
  EP_OVERLAY.classList.add('open');
  // On a CaPSA sidebar the trigger is only rendered when that admin also
  // holds an expert role, so it is not always there.
  if (EP_TRIGGER) EP_TRIGGER.classList.add('ep-open');
  renderTags();
  epUpdateSlotTotal();
  document.getElementById('ep-save-msg').style.display = 'none';
}

function closeExpertProfile(){
  EP_OVERLAY.classList.remove('open');
  if (EP_TRIGGER) EP_TRIGGER.classList.remove('ep-open');
}

function handleEpOverlay(e){
  if(e.target === EP_OVERLAY) closeExpertProfile();
}

function showEpSaveMsg(text, isError){
  const el = document.getElementById('ep-save-msg');
  el.textContent = text;
  el.style.display = 'block';
  el.style.background = isError ? 'rgba(220,38,38,0.08)' : 'rgba(22,163,74,0.08)';
  el.style.border = '1px solid ' + (isError ? 'rgba(220,38,38,0.25)' : 'rgba(22,163,74,0.2)');
  el.style.color = isError ? '#b91c1c' : '#15803d';
}

async function saveExpertProfile(){
  const btn = document.getElementById('ep-save-btn');
  const msg = document.getElementById('ep-save-msg');
  msg.style.display = 'none';

  // Slots for every program, including 0 for the ones they unticked - the
  // server needs to see a program drop to 0 to actually remove it.
  const programSlots = {};
  document.querySelectorAll('.ep-program-row').forEach(function (row) {
    const on = row.querySelector('.ep-program-check').checked;
    const n = Number(row.querySelector('.ep-program-slots').value) || 0;
    programSlots[row.dataset.program] = on ? n : 0;
  });

  if (Object.values(programSlots).reduce((a, b) => a + b, 0) < 1) {
    showEpSaveMsg('Pick at least one program and how many teams you take from it.', true);
    return;
  }

  btn.disabled = true;

  try {
    const res = await fetch('{{ route('instructor.profile.update') }}', {
      method: 'PUT',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      // epTags is the live expertise list this panel is showing. It used to
      // be left out of this request entirely, so every edit an expert made
      // to their expertise was silently thrown away on close.
      body: JSON.stringify({
        program_slots: programSlots,
        expertise: epTags,
        degree: (document.getElementById('ep-degree') || {}).value || null,
      }),
    });
    const json = await res.json();

    if (!res.ok || !json.success) {
      showEpSaveMsg(json.message || 'Could not save. Please try again.', true);
      return;
    }

    // Deliberately no reload and no auto-close. This panel IS where these
    // values are displayed, and the server just echoed back what it stored,
    // so there is nothing stale to refresh - reloading only made the page
    // blink and yank the panel shut before you could read the confirmation.
    showEpSaveMsg('Saved.', false);
  } catch (e) {
    showEpSaveMsg('Could not reach the server. Please try again.', true);
  } finally {
    btn.disabled = false;
  }
}

document.addEventListener('keydown', e => {
  if(e.key === 'Escape') closeExpertProfile();
});
</script>