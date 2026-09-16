<style>
  .tr-page{max-width:860px}
  .tr-head{margin-bottom:18px}
  .tr-title{font-size:22px;font-weight:700;color:var(--text);letter-spacing:-.01em}
  .tr-sub{font-size:13px;color:var(--text3);margin-top:3px}

  .tr-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:20px 22px;margin-bottom:16px}
  .tr-card-head{display:flex;gap:14px;align-items:flex-start;margin-bottom:16px}
  .tr-icon{width:42px;height:42px;border-radius:50%;background:var(--accent-light);color:var(--accent3);
           display:flex;align-items:center;justify-content:center;flex:none}
  .tr-icon svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .tr-card-title{display:flex;align-items:center;gap:10px;font-size:15px;font-weight:700;color:var(--text)}
  .tr-card-note{font-size:12.5px;color:var(--text3);margin-top:3px;line-height:1.5}
  .tr-badge{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;padding:2px 9px;
            border-radius:999px;background:var(--green-bg);color:var(--green)}
  .tr-badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor}

  .tr-inset{border:1px solid var(--border);border-radius:11px;padding:14px 16px;background:var(--bg3)}
  .tr-label{font-size:12.5px;font-weight:600;color:var(--text);margin-bottom:10px}
  .tr-sizes{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
  .tr-size{padding:11px 0;border:1px solid var(--border);border-radius:9px;background:var(--bg2);font:inherit;
           font-size:14px;font-weight:600;color:var(--text2);cursor:pointer;font-variant-numeric:tabular-nums;
           transition:border-color .15s,background .15s,color .15s}
  .tr-size:hover:not(.is-on){border-color:var(--accent);color:var(--text)}
  .tr-size.is-on{background:var(--accent);border-color:var(--accent);color:#18191f}
  .tr-size:focus-visible,.tr-switch:focus-visible,.tr-btn:focus-visible{outline:2px solid var(--accent3);outline-offset:2px}

  .tr-hint{display:flex;align-items:center;gap:8px;margin:12px 0 0;font-size:12.5px;color:var(--text2)}
  .tr-hint svg{width:15px;height:15px;flex:none;fill:none;stroke:var(--text3);stroke-width:2}
  .tr-impact{margin-top:10px;font-size:12px;line-height:1.5;color:var(--amber)}
  .tr-note{display:flex;gap:10px;margin-top:14px;padding:11px 13px;border-radius:9px;
           background:var(--bg4);font-size:12px;line-height:1.55;color:var(--text2)}
  .tr-note svg{width:16px;height:16px;flex:none;margin-top:1px;fill:none;stroke:var(--text3);stroke-width:2}

  .tr-rows{display:flex;flex-direction:column;gap:10px}
  .tr-row{display:flex;align-items:center;gap:14px;padding:12px 14px;border:1px solid var(--border);
          border-radius:11px;background:var(--bg3)}
  .tr-row .tr-icon{width:34px;height:34px;background:var(--bg2)}
  .tr-row .tr-icon svg{width:17px;height:17px}
  .tr-row-text{flex:1;min-width:0}
  .tr-row-name{font-size:13px;font-weight:600;color:var(--text)}
  .tr-row-sub{font-size:11.5px;color:var(--text3);margin-top:1px}
  .tr-switch-wrap{display:flex;align-items:center;gap:9px}
  .tr-switch{position:relative;width:42px;height:24px;border:none;border-radius:999px;background:var(--bg4);
             cursor:pointer;transition:background .15s;flex:none}
  .tr-switch::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;
                    background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.25);transition:transform .15s}
  .tr-switch[aria-checked="true"]{background:var(--navy-dark)}
  .tr-switch[aria-checked="true"]::after{transform:translateX(18px)}
  .tr-switch-state{font-size:12px;font-weight:600;color:var(--text2);min-width:22px}
  .tr-warn{display:flex;gap:10px;margin-top:12px;padding:11px 13px;border-radius:9px;
           background:var(--amber-bg);color:var(--amber);font-size:12px;line-height:1.5}
  .tr-warn svg{width:16px;height:16px;flex:none;margin-top:1px;fill:none;stroke:currentColor;stroke-width:2}

  .tr-actions{display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-top:4px}
  .tr-dirty{margin-right:auto;font-size:12px;color:var(--text3)}
  .tr-btn{font:inherit;font-size:13px;font-weight:600;padding:9px 16px;border-radius:9px;cursor:pointer;
          border:1px solid var(--border);background:var(--bg2);color:var(--text2)}
  .tr-btn:hover:not([disabled]){color:var(--text);border-color:var(--text3)}
  .tr-btn-primary{background:var(--navy-dark);border-color:var(--navy-dark);color:#fff}
  .tr-btn-primary:hover:not([disabled]){background:var(--navy-light);color:#fff}
  .tr-btn[disabled]{opacity:.45;cursor:default}

  /* Nothing saves until the CaPSA has read what it does. */
  .tr-modal{position:fixed;inset:0;z-index:120;display:none;align-items:center;justify-content:center;
            padding:20px;background:rgba(15,17,23,.45)}
  .tr-modal.show{display:flex}
  .tr-modal-box{width:100%;max-width:440px;background:var(--bg2);border:1px solid var(--border);
                border-radius:14px;padding:20px 22px;box-shadow:0 18px 44px -18px rgba(0,0,0,.45)}
  .tr-modal-title{font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px}
  .tr-modal-lede{font-size:12.5px;color:var(--text3);margin-bottom:12px}
  .tr-changes{margin:0 0 16px;padding-left:18px;font-size:13px;line-height:1.6;color:var(--text2)}
  .tr-changes li+li{margin-top:6px}
  .tr-changes .tr-effect{display:block;font-size:12px;color:var(--amber)}
  .tr-modal-actions{display:flex;justify-content:flex-end;gap:10px}

  @media (prefers-reduced-motion:reduce){.tr-switch,.tr-switch::after,.tr-size{transition:none}}
</style>

<div class="tr-page">

    <div class="tr-head">
      <div class="tr-sub">The rules every team in CapsTrack is formed under.</div>
    </div>

    {{-- Maximum team members --}}
    <section class="tr-card" aria-labelledby="tr-size-title">
      <div class="tr-card-head">
        <div class="tr-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div>
          <div class="tr-card-title" id="tr-size-title">Maximum Team Members <span class="tr-badge">Active</span></div>
          <div class="tr-card-note">The most students one team may have.</div>
        </div>
      </div>

      <div class="tr-inset">
        <div class="tr-label" id="tr-size-label">Maximum members per team</div>
        <div class="tr-sizes" role="radiogroup" aria-labelledby="tr-size-label">
          @for ($n = \App\Models\Team::MIN_MEMBERS; $n <= \App\Models\Team::MAX_MEMBERS; $n++)
            <button type="button" class="tr-size" role="radio" data-size="{{ $n }}" aria-checked="false">{{ $n }}</button>
          @endfor
        </div>

        <p class="tr-hint">
          <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
          <span>Teams can contain up to <b id="tr-size-now">{{ $maxMembers }}</b> students.</span>
        </p>
        <p class="tr-impact" id="tr-impact" hidden></p>

        <div class="tr-note">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          <div>
            This applies when a team is created or someone joins one. Teams already
            larger than a new limit keep their members, but cannot add anyone.
          </div>
        </div>
      </div>
    </section>

    {{-- Team creation --}}
    <section class="tr-card" aria-labelledby="tr-create-title">
      <div class="tr-card-head">
        <div class="tr-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
        <div>
          <div class="tr-card-title" id="tr-create-title">Team Creation</div>
          <div class="tr-card-note">Whether students can form and join teams on their own.</div>
        </div>
      </div>

      <div class="tr-rows">
        <div class="tr-row">
          <div class="tr-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg></div>
          <div class="tr-row-text">
            <div class="tr-row-name" id="tr-creation-name">Allow students to create teams</div>
            <div class="tr-row-sub">Off stops new teams, including ones started from a recruitment post.</div>
          </div>
          <div class="tr-switch-wrap">
            <button type="button" class="tr-switch" role="switch" id="tr-creation"
                    aria-labelledby="tr-creation-name" aria-checked="false"></button>
            <span class="tr-switch-state" id="tr-creation-state">Off</span>
          </div>
        </div>

        <div class="tr-row">
          <div class="tr-icon"><svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg></div>
          <div class="tr-row-text">
            <div class="tr-row-name" id="tr-joining-name">Allow students to join teams</div>
            <div class="tr-row-sub">Off stops join requests, invitations, and accepting ones already sent.</div>
          </div>
          <div class="tr-switch-wrap">
            <button type="button" class="tr-switch" role="switch" id="tr-joining"
                    aria-labelledby="tr-joining-name" aria-checked="false"></button>
            <span class="tr-switch-state" id="tr-joining-state">Off</span>
          </div>
        </div>
      </div>

      <div class="tr-warn">
        <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <div>Changing these rules affects how every student forms a team.</div>
      </div>
    </section>

    <div class="tr-actions">
      <span class="tr-dirty" id="tr-dirty" hidden>Unsaved changes</span>
      <button type="button" class="tr-btn" id="tr-discard" disabled>Discard changes</button>
      <button type="button" class="tr-btn tr-btn-primary" id="tr-save" disabled>Save changes</button>
    </div>

  </div>

<div class="tr-modal" id="tr-confirm" role="dialog" aria-modal="true" aria-labelledby="tr-confirm-title">
  <div class="tr-modal-box">
    <div class="tr-modal-title" id="tr-confirm-title">Save team rules?</div>
    <div class="tr-modal-lede">These take effect for every student straight away.</div>
    <ul class="tr-changes" id="tr-changes"></ul>
    <div class="tr-modal-actions">
      <button type="button" class="tr-btn" id="tr-confirm-cancel">Cancel</button>
      <button type="button" class="tr-btn tr-btn-primary" id="tr-confirm-save">Save changes</button>
    </div>
  </div>
</div>

<div id="tr-toast" class="toast"></div>

<script>
(function () {
  // What is stored, and what is on screen. Nothing reaches the server until
  // Save is pressed and the change has been read in the confirmation.
  let saved = {
    max: @json($maxMembers),
    creation: @json($allowCreation),
    joining: @json($allowJoining),
  };
  let draft = Object.assign({}, saved);

  // { headcount: number of teams with that many members }
  const teamSizes = @json((object) $teamSizes);

  const $ = (id) => document.getElementById(id);
  const sizeButtons = Array.from(document.querySelectorAll('.tr-size'));

  function teamsAbove(limit) {
    return Object.keys(teamSizes).reduce(
      (n, size) => n + (Number(size) > limit ? Number(teamSizes[size]) : 0), 0);
  }

  function isDirty() {
    return draft.max !== saved.max || draft.creation !== saved.creation || draft.joining !== saved.joining;
  }

  function render() {
    sizeButtons.forEach((b) => {
      const on = Number(b.dataset.size) === draft.max;
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-checked', on ? 'true' : 'false');
      b.tabIndex = on ? 0 : -1;
    });
    $('tr-size-now').textContent = draft.max;

    const over = teamsAbove(draft.max);
    const impact = $('tr-impact');
    impact.hidden = over === 0 || draft.max >= saved.max;
    impact.textContent = over + ' team' + (over === 1 ? ' already has' : 's already have')
      + ' more than ' + draft.max + ' members. They keep them, but cannot add anyone.';

    [['creation', 'tr-creation'], ['joining', 'tr-joining']].forEach(([key, id]) => {
      $(id).setAttribute('aria-checked', draft[key] ? 'true' : 'false');
      $(id + '-state').textContent = draft[key] ? 'On' : 'Off';
    });

    const dirty = isDirty();
    $('tr-save').disabled = !dirty;
    $('tr-discard').disabled = !dirty;
    $('tr-dirty').hidden = !dirty;
  }

  sizeButtons.forEach((b) => b.addEventListener('click', () => { draft.max = Number(b.dataset.size); render(); }));

  // Arrow keys move through the sizes, as a radio group should.
  document.querySelector('.tr-sizes').addEventListener('keydown', (e) => {
    if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) return;
    e.preventDefault();
    const i = sizeButtons.findIndex((b) => Number(b.dataset.size) === draft.max);
    const step = (e.key === 'ArrowLeft' || e.key === 'ArrowUp') ? -1 : 1;
    const next = sizeButtons[Math.min(sizeButtons.length - 1, Math.max(0, i + step))];
    draft.max = Number(next.dataset.size);
    render();
    next.focus();
  });

  $('tr-creation').addEventListener('click', () => { draft.creation = !draft.creation; render(); });
  $('tr-joining').addEventListener('click', () => { draft.joining = !draft.joining; render(); });

  $('tr-discard').addEventListener('click', () => { draft = Object.assign({}, saved); render(); });

  function changeList() {
    const items = [];
    if (draft.max !== saved.max) {
      const over = teamsAbove(draft.max);
      items.push({
        text: 'Maximum members per team: ' + saved.max + ' → ' + draft.max,
        effect: draft.max < saved.max && over > 0
          ? over + ' team' + (over === 1 ? '' : 's') + ' over the new limit will keep their members but cannot add anyone.'
          : '',
      });
    }
    if (draft.creation !== saved.creation) {
      items.push({
        text: 'Students ' + (draft.creation ? 'can' : 'cannot') + ' create teams',
        effect: draft.creation ? '' : 'Anyone without a team will not be able to start one.',
      });
    }
    if (draft.joining !== saved.joining) {
      items.push({
        text: 'Students ' + (draft.joining ? 'can' : 'cannot') + ' join teams',
        effect: draft.joining ? '' : 'Pending invitations and join requests cannot be accepted while this is off.',
      });
    }
    return items;
  }

  function openConfirm() {
    const list = $('tr-changes');
    list.innerHTML = '';
    changeList().forEach((c) => {
      const li = document.createElement('li');
      li.textContent = c.text;
      if (c.effect) {
        const e = document.createElement('span');
        e.className = 'tr-effect';
        e.textContent = c.effect;
        li.appendChild(e);
      }
      list.appendChild(li);
    });
    $('tr-confirm').classList.add('show');
    $('tr-confirm-save').focus();
  }

  function closeConfirm() {
    $('tr-confirm').classList.remove('show');
    $('tr-save').focus();
  }

  function toast(message, ok) {
    const el = $('tr-toast');
    el.textContent = message;
    el.style.borderLeftColor = ok ? 'var(--green)' : 'var(--red)';
    el.classList.add('show');
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => el.classList.remove('show'), 3200);
  }

  $('tr-save').addEventListener('click', openConfirm);
  $('tr-confirm-cancel').addEventListener('click', closeConfirm);
  $('tr-confirm').addEventListener('click', (e) => { if (e.target.id === 'tr-confirm') closeConfirm(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && $('tr-confirm').classList.contains('show')) closeConfirm();
  });

  $('tr-confirm-save').addEventListener('click', () => {
    const btn = $('tr-confirm-save');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    fetch(@json(route('capsa.team_rules.update'), JSON_UNESCAPED_SLASHES), {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({
        max_members: draft.max,
        allow_creation: draft.creation,
        allow_joining: draft.joining,
      }),
    })
      .then((r) => r.json().then((data) => ({ ok: r.ok, data })))
      .then(({ ok, data }) => {
        if (!ok || !data.success) {
          toast(data.message || 'Could not save the team rules.', false);
          return;
        }
        saved = { max: data.max_members, creation: data.allow_creation, joining: data.allow_joining };
        draft = Object.assign({}, saved);
        render();
        $('tr-confirm').classList.remove('show');
        toast(data.message, true);
      })
      .catch(() => toast('Network error. Nothing was saved.', false))
      .finally(() => { btn.disabled = false; btn.textContent = 'Save changes'; });
  });

  // Leaving with a rule changed but not saved is almost always a mistake.
  window.addEventListener('beforeunload', (e) => {
    if (isDirty()) { e.preventDefault(); e.returnValue = ''; }
  });

  render();
})();
</script>
