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
  .ep-modal{position:fixed;top:0;left:250px;width:300px;height:100vh;background:#fff;box-shadow:4px 0 24px rgba(0,0,0,0.18);overflow-y:auto;pointer-events:all;transform:translateX(-10px);opacity:0;transition:transform 0.18s ease,opacity 0.18s ease;z-index:201;border-right:1px solid var(--border)}
  .ep-overlay.open .ep-modal{transform:translateX(0);opacity:1}
  .ep-header{background:var(--navy-dark);padding:22px 18px 18px;border-bottom:2px solid var(--accent);display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:1}
  .ep-avatar{width:44px;height:44px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:#18191f;flex-shrink:0;border:2px solid rgba(201,168,44,0.4)}
  .ep-header-name{font-size:13px;font-weight:600;color:var(--navy-text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .ep-header-sub{font-size:11px;color:var(--accent);margin-top:2px;font-weight:500}
  .ep-close{background:none;border:none;cursor:pointer;color:rgba(255,255,255,0.4);padding:4px;line-height:1;display:flex;margin-left:auto;flex-shrink:0;transition:color 0.13s}
  .ep-close:hover{color:rgba(255,255,255,0.85)}
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
  .ep-tag{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:6px;font-size:11.5px;font-weight:500;background:var(--blue-bg);color:var(--blue);border:1px solid rgba(29,78,216,0.18)}
  .ep-tag-remove{background:none;border:none;cursor:pointer;display:flex;align-items:center;padding:0;color:var(--blue);opacity:0.55;line-height:1;transition:opacity 0.13s}
  .ep-tag-remove:hover{opacity:1}
  .ep-tag-remove svg{width:10px;height:10px;stroke:currentColor;fill:none;stroke-width:2.5}
  .ep-tag-input-row{display:flex;gap:6px}
  .ep-tag-input{flex:1;background:#fff;border:1.5px solid var(--border);border-radius:8px;padding:7px 11px;font-size:12px;font-family:'DM Sans',sans-serif;color:var(--text);outline:none;transition:border 0.15s}
  .ep-tag-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(201,168,44,0.1)}
  .ep-tag-input::placeholder{color:var(--text3)}
  .ep-tag-add-btn{background:var(--accent);border:none;border-radius:8px;padding:7px 12px;font-size:11.5px;font-weight:700;color:#18191f;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background 0.13s;white-space:nowrap}
  .ep-tag-add-btn:hover{background:var(--accent2)}
  .ep-suggest{
    display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;
    border:1.5px solid var(--border);border-radius:8px;box-shadow:0 10px 24px -6px rgba(0,0,0,0.16);
    max-height:190px;overflow-y:auto;z-index:30;scrollbar-width:none;
  }
  .ep-suggest::-webkit-scrollbar{display:none}
  .ep-suggest.open{display:block}
  .ep-suggest-group{
    padding:6px 11px 3px;font-size:9.5px;font-weight:700;color:var(--text3);
    text-transform:uppercase;letter-spacing:0.8px;
  }
  .ep-suggest button{
    display:block;width:100%;text-align:left;padding:6px 11px;border:none;background:none;
    font-size:12px;color:var(--text);cursor:pointer;font-family:'DM Sans',sans-serif;
  }
  .ep-suggest button:hover{background:var(--bg3)}
  .ep-suggest .ep-suggest-empty{padding:9px 11px;font-size:11.5px;color:var(--text3)}

  .ep-quick-groups{margin-top:10px;display:flex;flex-direction:column;gap:7px}
  .ep-quick-group-title{
    font-size:9.5px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.8px;
  }
  .ep-quick-chips{display:flex;flex-wrap:wrap;gap:5px}
  .ep-quick-chip{
    font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;padding:4px 9px;border-radius:999px;
    border:1.5px dashed var(--border);background:#fff;color:var(--text2);cursor:pointer;
  }
  .ep-quick-chip:hover{border-style:solid;border-color:var(--accent);color:var(--accent3);background:rgba(201,168,44,0.07)}
  .ep-quick-chip.picked{
    border-style:solid;border-color:var(--blue);background:var(--blue-bg);color:var(--blue);cursor:default;
  }
  .ep-footer{padding:12px 18px 16px;border-top:1px solid var(--border);display:flex;gap:8px;position:sticky;bottom:0;background:#fff;z-index:1}
  .ep-footer .btn{flex:1;justify-content:center;font-size:12px;padding:7px 10px}
  .role-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:22px;font-size:11px;font-weight:700;letter-spacing:0.3px;text-transform:uppercase}
  .role-pill svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5;flex-shrink:0}
  .role-adviser{background:rgba(201,168,44,0.12);color:var(--accent3);border:1px solid rgba(201,168,44,0.3)}
  .role-consultant{background:var(--cyan-bg);color:var(--cyan);border:1px solid rgba(8,145,178,0.2)}
  .role-grammarian{background:var(--green-bg);color:var(--green);border:1px solid rgba(22,163,74,0.2)}
</style>
@endonce

<!-- EXPERT PROFILE SIDE PANEL -->
<div class="ep-overlay" id="ep-overlay" onclick="handleEpOverlay(event)">
  <div class="ep-modal" id="ep-modal">

    <!-- Sticky Header -->
    <div class="ep-header">
      <div class="ep-avatar" style="padding:0;overflow:hidden;">
        <img src="{{ $expert?->avatarUrl() ?? asset('images/default-avatar.jpg') }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;" loading="lazy">
      </div>
      <div style="flex:1;min-width:0">
        <div class="ep-header-name">{{ $expert?->first_name }} {{ $expert?->last_name }}</div>
        <div class="ep-header-sub">Capstone Expert &middot; Active</div>
      </div>
      <button class="ep-close" onclick="closeExpertProfile()">
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
              <span class="role-pill role-{{ $role->role_name }}">{{ ucfirst($role->role_name) }}</span>
            @endforeach
          </div>
        <div style="font-size:11px;color:var(--text3);margin-top:8px;display:flex;align-items:center;gap:4px">
          <svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Managed by the system administrator
        </div>
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
        <div class="ep-tags-wrap" id="ep-tags-wrap">
          @php
            $expertiseList = \App\Models\ExpertExpertise::where('expert_id', $expert?->expert_id)->pluck('expertise_field')->toArray();
          @endphp
          @foreach($expertiseList as $exp)
            <span class="ep-tag">{{ $exp }}
              <button class="ep-tag-remove" onclick="removeTag({{ $loop->index }})" title="Remove">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
              </button>
            </span>
          @endforeach
        </div>
        <div class="ep-tag-input-row" style="position:relative">
          <input class="ep-tag-input" id="ep-tag-input" type="text" autocomplete="off"
                 placeholder="Search skills, or type your own..."
                 onkeydown="handleTagKey(event)" oninput="epFilterSuggestions(this.value)"
                 onfocus="epFilterSuggestions(this.value)"/>
          <button class="ep-tag-add-btn" onclick="addTag()">+ Add</button>
          <div class="ep-suggest" id="ep-suggest"></div>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:7px">
          Pick from the list, or type anything that isn't there and press Enter.
        </div>

        <div class="ep-quick-groups" id="ep-quick-groups"></div>
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
const EP_TAGS_EL = document.getElementById('ep-tags-wrap');
const EP_INPUT   = document.getElementById('ep-tag-input');

let epTags = @json($expertiseList ?? []);

// Suggested skills, grouped by the kind of work they belong to. This is a
// starting point, not a whitelist - anything typed that isn't here is still
// accepted. Free text alone meant every expert invented their own wording
// ("UI/UX", "ui ux design", "User Interface"), which made the lists students
// see impossible to scan or match on.
// Sourced from App\Support\SkillCatalog so the suggestions offered here and
// the groups the student expert list filters by can never drift apart.
const EP_SKILL_GROUPS = Object.entries(@json(\App\Support\SkillCatalog::groups()))
  .map(([name, items]) => ({ name, items }));

const EP_ALL_SKILLS = EP_SKILL_GROUPS.flatMap(g => g.items.map(i => ({ name: i, group: g.name })));

function epNorm(v){ return String(v).trim().toLowerCase(); }
function epHasTag(name){ return epTags.some(t => epNorm(t) === epNorm(name)); }

function epFilterSuggestions(query){
  const box = document.getElementById('ep-suggest');
  const q = epNorm(query || '');
  const matches = EP_ALL_SKILLS.filter(s => !epHasTag(s.name) && (!q || epNorm(s.name).includes(q)));

  if (!matches.length) {
    box.innerHTML = q
      ? '<div class="ep-suggest-empty">Nothing matches - press Enter to add "' + query.trim() + '" anyway.</div>'
      : '';
    box.classList.toggle('open', !!q);
    return;
  }

  let html = '', lastGroup = null;
  matches.slice(0, 40).forEach(function (s) {
    if (s.group !== lastGroup) { html += '<div class="ep-suggest-group">' + s.group + '</div>'; lastGroup = s.group; }
    html += '<button type="button" onclick="epPickSkill(' + JSON.stringify(s.name).replace(/"/g, '&quot;') + ')">' + s.name + '</button>';
  });
  box.innerHTML = html;
  box.classList.add('open');
}

function epPickSkill(name){
  if (!epHasTag(name)) { epTags.push(name); renderTags(); }
  const input = document.getElementById('ep-tag-input');
  input.value = '';
  document.getElementById('ep-suggest').classList.remove('open');
  epRenderQuickGroups();
  input.focus();
}

// The same list, browsable without typing - for people who would rather
// recognise a skill than recall one.
function epRenderQuickGroups(){
  const wrap = document.getElementById('ep-quick-groups');
  if (!wrap) return;
  wrap.innerHTML = EP_SKILL_GROUPS.map(function (g) {
    const chips = g.items.map(function (i) {
      const picked = epHasTag(i);
      return '<button type="button" class="ep-quick-chip' + (picked ? ' picked' : '') + '"'
           + (picked ? ' disabled' : ' onclick="epPickSkill(' + JSON.stringify(i).replace(/"/g, '&quot;') + ')"')
           + '>' + (picked ? '✓ ' : '+ ') + i + '</button>';
    }).join('');
    return '<div><div class="ep-quick-group-title">' + g.name + '</div><div class="ep-quick-chips">' + chips + '</div></div>';
  }).join('');
}

document.addEventListener('click', function (e) {
  if (!e.target.closest('.ep-tag-input-row')) {
    const box = document.getElementById('ep-suggest');
    if (box) box.classList.remove('open');
  }
});

function renderTags(){
  EP_TAGS_EL.innerHTML = '';
  epTags.forEach((tag, i) => {
    const span = document.createElement('span');
    span.className = 'ep-tag';
    span.innerHTML = `${tag}
      <button class="ep-tag-remove" onclick="removeTag(${i})" title="Remove">
        <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>`;
    EP_TAGS_EL.appendChild(span);
  });

  // Single place every add/remove funnels through, so the browsable chips
  // stay in step no matter which route added the tag.
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
  document.getElementById('ep-suggest').classList.remove('open');
  renderTags();
  EP_INPUT.focus();
}

function removeTag(i){ epTags.splice(i,1); renderTags(); }
function handleTagKey(e){ if(e.key==='Enter'){ e.preventDefault(); addTag(); } }

function epToggleProgram(cb){
  var row = cb.closest('.ep-program-row');
  var num = row.querySelector('.ep-program-slots');
  row.classList.toggle('on', cb.checked);
  num.disabled = !cb.checked;
  if (cb.checked && (!num.value || Number(num.value) < 1)) num.value = 1;
  epUpdateSlotTotal();
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
  EP_TRIGGER.classList.add('ep-open');
  renderTags();
  epUpdateSlotTotal();
  document.getElementById('ep-save-msg').style.display = 'none';
}

function closeExpertProfile(){
  EP_OVERLAY.classList.remove('open');
  EP_TRIGGER.classList.remove('ep-open');
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
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      // epTags is the live expertise list this panel is showing. It used to
      // be left out of this request entirely, so every edit an expert made
      // to their expertise was silently thrown away on close.
      body: JSON.stringify({ program_slots: programSlots, expertise: epTags }),
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