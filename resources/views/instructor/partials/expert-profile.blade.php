@php $expert = auth('expert')->user(); @endphp

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

      <!-- Max Teams -->
      <div class="ep-field">
        <div class="ep-label">Team Capacity</div>
        <div class="ep-value ep-editable">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          Max <input type="number" id="ep-max-teams" class="ep-number-input" min="1" max="10" value="{{ $expert?->max_teams ?? 4 }}"> teams
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:6px">How many teams you can be assigned to at once (1–10).</div>
      </div>

      <div class="ep-divider"></div>

      @if($expert?->department)
      <div class="ep-field">
        <div class="ep-label">Department</div>
        <div class="ep-value readonly">
          <svg viewBox="0 0 24 24"><path d="M21 10H3V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5zM3 10h18v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V10"/></svg>
          {{ $expert?->department }}
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
        <div class="ep-tag-input-row">
          <input class="ep-tag-input" id="ep-tag-input" type="text" placeholder="Add expertise..." onkeydown="handleTagKey(event)"/>
          <button class="ep-tag-add-btn" onclick="addTag()">+ Add</button>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:7px">Press Enter or click Add. Click x to remove.</div>
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
}

function addTag(){
  const val = EP_INPUT.value.trim();
  if(!val) return;
  if(epTags.map(t=>t.toLowerCase()).includes(val.toLowerCase())){ EP_INPUT.select(); return; }
  epTags.push(val);
  EP_INPUT.value = '';
  renderTags();
  EP_INPUT.focus();
}

function removeTag(i){ epTags.splice(i,1); renderTags(); }
function handleTagKey(e){ if(e.key==='Enter'){ e.preventDefault(); addTag(); } }

function openExpertProfile(){
  EP_OVERLAY.classList.add('open');
  EP_TRIGGER.classList.add('ep-open');
  renderTags();
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
  const input = document.getElementById('ep-max-teams');
  const msg = document.getElementById('ep-save-msg');
  msg.style.display = 'none';
  btn.disabled = true;

  try {
    const res = await fetch('{{ route('instructor.profile.update') }}', {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ max_teams: input.value }),
    });
    const json = await res.json();

    if (!res.ok || !json.success) {
      showEpSaveMsg(json.message || 'Could not save. Please try again.', true);
      return;
    }

    input.value = json.max_teams;
    showEpSaveMsg('Saved!', false);
    setTimeout(closeExpertProfile, 700);
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