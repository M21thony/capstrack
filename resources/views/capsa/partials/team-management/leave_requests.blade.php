<style nonce="{{ Vite::cspNonce() }}">
  .rr-page{max-width:900px}
  .rr-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px}
  .rr-title{font-size:22px;font-weight:700;color:var(--text);letter-spacing:-.01em}
  .rr-sub{font-size:13px;color:var(--text3);margin-top:3px;max-width:60ch;line-height:1.5}
  .rr-locked{display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:var(--text2);
             padding:6px 11px;border:1px solid var(--border);border-radius:999px;background:var(--bg2);white-space:nowrap}
  .rr-locked svg,.rr-ico{width:14px;height:14px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex:none}

  .rr-tabs{display:inline-flex;gap:4px;padding:4px;border:1px solid var(--border);border-radius:10px;background:var(--bg2);margin-bottom:14px}
  .rr-tab{font:inherit;font-size:13px;font-weight:600;padding:7px 14px;border:none;border-radius:7px;background:none;color:var(--text3);cursor:pointer}
  .rr-tab[aria-selected="true"]{background:var(--bg3);color:var(--text)}
  .rr-count{font-variant-numeric:tabular-nums;margin-left:5px;padding:1px 7px;border-radius:999px;background:var(--bg4);font-size:11px}
  .rr-tab[aria-selected="true"] .rr-count.has{background:var(--red);color:#fff}

  .rr-list{display:flex;flex-direction:column;gap:12px}
  .rr-card{background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:18px 20px}
  .rr-top{display:flex;gap:12px;align-items:flex-start}
  .rr-avatar{width:40px;height:40px;border-radius:50%;flex:none;overflow:hidden;background:var(--bg4);color:var(--text2);
             display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700}
  .rr-avatar img{width:100%;height:100%;object-fit:cover}
  .rr-main{flex:1;min-width:0}
  .rr-line{font-size:14px;color:var(--text);line-height:1.45}
  .rr-line b{font-weight:700}
  .rr-meta{display:flex;flex-wrap:wrap;gap:4px 12px;margin-top:4px;font-size:12px;color:var(--text3)}
  .rr-type{display:inline-flex;align-items:center;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;flex:none;
           background:var(--bg4);color:var(--text2)}
  .rr-type.remove{background:var(--red-bg);color:var(--red)}

  .rr-reason{margin:12px 0 0;padding:11px 14px;border-radius:10px;background:var(--bg3);font-size:13px;line-height:1.6;
             color:var(--text2);white-space:pre-line;overflow-wrap:anywhere}
  .rr-reason-label{display:block;font-size:11px;font-weight:600;color:var(--text3);margin-bottom:3px}
  .rr-effect{display:flex;gap:8px;align-items:center;margin-top:10px;font-size:12.5px;color:var(--text2)}
  .rr-effect.warn{color:var(--amber)}

  .rr-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}
  .rr-btn{font:inherit;font-size:13px;font-weight:600;padding:8px 15px;border-radius:9px;cursor:pointer;
          border:1px solid var(--border);background:var(--bg2);color:var(--text2)}
  .rr-btn:hover:not([disabled]){color:var(--text);border-color:var(--text3)}
  .rr-btn-primary{background:var(--navy-dark);border-color:var(--navy-dark);color:#fff}
  .rr-btn-primary:hover:not([disabled]){background:var(--navy-light);color:#fff}
  .rr-btn-danger{background:var(--red);border-color:var(--red);color:#fff}
  .rr-btn-danger:hover:not([disabled]){opacity:.9;color:#fff}
  .rr-btn[disabled]{opacity:.45;cursor:default}
  .rr-btn:focus-visible,.rr-tab:focus-visible,.rr-note-input:focus-visible{outline:2px solid var(--accent3);outline-offset:2px}

  .rr-decided{display:grid;grid-template-columns:auto 1fr auto;gap:12px;align-items:start;padding:14px 18px}
  .rr-status{font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;white-space:nowrap}
  .rr-status.approved{background:var(--green-bg);color:var(--green)}
  .rr-status.declined{background:var(--red-bg);color:var(--red)}
  .rr-status.withdrawn{background:var(--bg4);color:var(--text3)}
  .rr-status.auto{background:var(--amber-bg);color:var(--amber)}
  .rr-auto-tag{display:inline-flex;align-items:center;gap:5px;font-weight:600;color:var(--amber)}
  .rr-due{display:inline-flex;align-items:center;gap:5px;padding:1px 8px;border-radius:999px;font-weight:700;background:var(--amber-bg);color:var(--amber)}
  .rr-due.soon{background:var(--red-bg);color:var(--red)}
  .rr-decision{margin-top:6px;font-size:12.5px;color:var(--text2);line-height:1.5;overflow-wrap:anywhere}

  .rr-empty{padding:34px 20px;text-align:center;border:1px dashed var(--border);border-radius:14px;color:var(--text3);font-size:13px;line-height:1.6}
  .rr-empty b{display:block;color:var(--text);font-size:14px;margin-bottom:4px}

  .rr-modal{position:fixed;inset:0;z-index:120;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,17,23,.45)}
  .rr-modal.show{display:flex}
  .rr-modal-box{width:100%;max-width:460px;background:var(--bg2);border:1px solid var(--border);border-radius:14px;
                padding:20px 22px;box-shadow:0 18px 44px -18px rgba(0,0,0,.45)}
  .rr-modal-title{font-size:15px;font-weight:700;color:var(--text)}
  .rr-modal-lede{font-size:12.5px;color:var(--text3);margin:4px 0 12px;line-height:1.5}
  .rr-effects{margin:0 0 14px;padding-left:18px;font-size:13px;line-height:1.6;color:var(--text2)}
  .rr-effects li+li{margin-top:4px}
  .rr-effects .warn{color:var(--amber)}
  .rr-note-label{display:block;font-size:12.5px;font-weight:600;color:var(--text);margin-bottom:6px}
  .rr-note-input{width:100%;box-sizing:border-box;min-height:88px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;
                 background:var(--bg3);color:var(--text);font:inherit;font-size:13px;line-height:1.5;resize:vertical}
  .rr-note-hint{display:flex;justify-content:space-between;font-size:11.5px;color:var(--text3);margin-top:4px}
  .rr-error{margin-top:10px;font-size:12.5px;color:var(--red)}
  .rr-modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}
</style>

<div class="rr-page">

    <div class="rr-head">
      <div>
        <div class="rr-sub">A member can only leave a team with your approval, and once a team passes Title Defense its leader can't remove anyone either. Those requests, and teams asking to change an expert who already took them on, come to you with a reason. A request to leave or remove a member that you do not decide within {{ \App\Models\MemberLeaveRequest::DECIDE_WITHIN_DAYS }} days is declined automatically, and the team is told it ran out of time.</div>
      </div>
      <span class="rr-locked">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        {{ $lockedTeams }} locked {{ $lockedTeams === 1 ? 'team' : 'teams' }} in your sections
      </span>
    </div>

    <div class="rr-tabs" role="tablist" aria-label="Requests">
      <button type="button" class="rr-tab" role="tab" id="tab-pending" aria-controls="panel-pending" aria-selected="true">
        Waiting<span class="rr-count {{ $pending->count() + $expertPending->count() ? 'has' : '' }}" id="count-pending">{{ $pending->count() + $expertPending->count() }}</span>
      </button>
      <button type="button" class="rr-tab" role="tab" id="tab-decided" aria-controls="panel-decided" aria-selected="false" tabindex="-1">
        Decided<span class="rr-count" id="count-decided">{{ $decided->count() + $expertDecided->count() }}</span>
      </button>
    </div>

    <section id="panel-pending" role="tabpanel" aria-labelledby="tab-pending">
      <div class="rr-list" id="pending-list">
        @foreach($pending as $r)
          @php
            $memberName = trim(($r->student->f_name ?? '').' '.($r->student->l_name ?? '')) ?: 'A member';
            $requesterName = trim(($r->requester->f_name ?? '').' '.($r->requester->l_name ?? '')) ?: 'The leader';
            $now = (int) ($memberCounts[$r->team_id] ?? 0);
            $after = max(0, $now - 1);
            $belowMin = $after < \App\Models\Team::MIN_MEMBERS;
            $lockedAt = $r->team->teamLockedAt();
            $isLeave = $r->type === \App\Models\MemberLeaveRequest::LEAVE;
          @endphp
          <article class="rr-card" id="req-{{ $r->id }}"
                   data-id="{{ $r->id }}"
                   data-member="{{ $memberName }}"
                   data-team="{{ $r->team->team_name }}"
                   data-type="{{ $r->type }}"
                   data-now="{{ $now }}"
                   data-after="{{ $after }}"
                   data-below="{{ $belowMin ? 1 : 0 }}"
                   data-requester="{{ $requesterName }}"
                   data-reason="{{ $r->reason }}"
                   data-created="{{ $r->created_at->format('M j, Y') }}">
            <div class="rr-top">
              <div class="rr-avatar" aria-hidden="true">
                @if($r->student?->avatarUrl())
                  <img src="{{ $r->student->avatarUrl() }}" alt="">
                @else
                  {{ $r->student?->avatarInitials() ?? '?' }}
                @endif
              </div>
              <div class="rr-main">
                <div class="rr-line">
                  @if($isLeave)
                    <b>{{ $memberName }}</b> wants to leave <b>{{ $r->team->team_name }}</b>
                  @else
                    <b>{{ $requesterName }}</b> (leader) asks to remove <b>{{ $memberName }}</b> from <b>{{ $r->team->team_name }}</b>
                  @endif
                </div>
                <div class="rr-meta">
                  <span>{{ $sectionOf[$r->id] }}</span>
                  <span title="{{ $r->created_at->format('M j, Y g:i A') }}">Asked {{ $r->created_at->diffForHumans() }}</span>
                  @if($lockedAt)<span>Locked since {{ $lockedAt->format('M j, Y') }}</span>@endif
                  @php
                    $deadline = $r->autoDeclinesAt();
                    $hoursLeft = max(0, (int) ceil(now()->diffInMinutes($deadline, false) / 60));
                  @endphp
                  <span class="rr-due {{ $hoursLeft <= 24 ? 'soon' : '' }}" title="Not decided by {{ $deadline->format('M j, Y g:i A') }}, it is declined automatically">
                    <svg class="rr-ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    Auto-declines {{ $hoursLeft <= 24 ? 'in '.max(1, $hoursLeft).' '.(max(1, $hoursLeft) === 1 ? 'hour' : 'hours') : 'on '.$deadline->format('M j') }}
                  </span>
                </div>
              </div>
              <span class="rr-type {{ $isLeave ? '' : 'remove' }}">{{ $isLeave ? 'Leaving' : 'Removal' }}</span>
            </div>

            <p class="rr-reason"><span class="rr-reason-label">{{ $isLeave ? 'Their reason' : 'Leader\'s reason' }}</span>{{ $r->reason }}</p>

            <div class="rr-effect {{ $belowMin ? 'warn' : '' }}">
              <svg class="rr-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <span>
                If approved, the team goes from {{ $now }} to {{ $after }} {{ $after === 1 ? 'member' : 'members' }}{{ $belowMin ? ', below the minimum of '.\App\Models\Team::MIN_MEMBERS.'. No one can be added to replace them' : '' }}.
              </span>
            </div>

            <div class="rr-actions">
              <button type="button" class="rr-btn" data-decide="declined">Decline</button>
              <button type="button" class="rr-btn rr-btn-primary" data-decide="approved">Approve</button>
            </div>
          </article>
        @endforeach
        @foreach($expertPending as $r)
          @php
            $expertName = $r->expert?->name ?? 'An expert';
            $role = $r->assignment?->engaged_role_name ?: 'expert';
            $requesterName = trim(($r->requester->f_name ?? '').' '.($r->requester->l_name ?? '')) ?: 'The leader';
            $initials = collect(explode(' ', $expertName))->filter()->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
          @endphp
          <article class="rr-card" id="ereq-{{ $r->id }}"
                   data-id="{{ $r->id }}"
                   data-url="{{ route('capsa.expert_removal_requests.update', $r) }}"
                   data-member="{{ $expertName }}"
                   data-role="{{ $role }}"
                   data-team="{{ $r->team->team_name }}"
                   data-type="expert"
                   data-replacement="{{ $r->replacement?->name }}"
                   data-requester="{{ $requesterName }}">
            <div class="rr-top">
              <div class="rr-avatar" aria-hidden="true">{{ $initials ?: '?' }}</div>
              <div class="rr-main">
                <div class="rr-line">
                  <b>{{ $requesterName }}</b> (leader) asks to remove <b>{{ $expertName }}</b> as {{ $role }} of <b>{{ $r->team->team_name }}</b>
                </div>
                <div class="rr-meta">
                  <span>{{ $expertSectionOf[$r->id] }}</span>
                  <span title="{{ $r->created_at->format('M j, Y g:i A') }}">Asked {{ $r->created_at->diffForHumans() }}</span>
                </div>
              </div>
              <span class="rr-type remove">Expert change</span>
            </div>

            <p class="rr-reason"><span class="rr-reason-label">Leader's reason</span>{{ $r->reason }}</p>
            @if($r->replacement)
              <p class="rr-reason"><span class="rr-reason-label">Replacement they picked</span><b>{{ $r->replacement->name }}</b> · their application goes out as soon as you approve, if they still have a slot.</p>
            @endif

            <div class="rr-actions">
              <button type="button" class="rr-btn" data-decide="declined">Decline</button>
              <button type="button" class="rr-btn rr-btn-primary" data-decide="approved">Approve</button>
            </div>
          </article>
        @endforeach
      </div>
      <div class="rr-empty" id="pending-empty" @if($pending->isNotEmpty() || $expertPending->isNotEmpty()) hidden @endif>
        <b>Nothing waiting</b>
        When a member asks to leave, a leader of a locked team asks to remove someone, or a team asks to change an expert, it shows up here.
      </div>
    </section>

    <section id="panel-decided" role="tabpanel" aria-labelledby="tab-decided" hidden>
      <div class="rr-list" id="decided-list">
        @foreach($decided as $r)
          @php
            $memberName = trim(($r->student->f_name ?? '').' '.($r->student->l_name ?? '')) ?: 'A member';
            $decider = trim(($r->decider->first_name ?? '').' '.($r->decider->last_name ?? ''));
            $label = $r->auto_declined ? 'Auto-declined' : (['approved' => 'Approved', 'declined' => 'Declined', 'withdrawn' => 'Withdrawn'][$r->status] ?? ucfirst($r->status));
          @endphp
          <article class="rr-card rr-decided">
            <span class="rr-status {{ $r->status }}{{ $r->auto_declined ? ' auto' : '' }}">{{ $label }}</span>
            <div class="rr-main">
              <div class="rr-line">
                {{ $r->type === 'leave' ? 'Leaving' : 'Removal' }}: <b>{{ $memberName }}</b> · {{ $r->team?->team_name }}
              </div>
              <div class="rr-meta">
                <span>{{ $sectionOf[$r->id] ?? '' }}</span>
                @if($r->status === 'withdrawn')
                  <span>Withdrawn by the student</span>
                @elseif($r->auto_declined)
                  <span class="rr-auto-tag">
                    <svg class="rr-ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    No action from CaPSA/TSA in {{ \App\Models\MemberLeaveRequest::DECIDE_WITHIN_DAYS }} days
                  </span>
                @elseif($decider)
                  <span>By {{ $decider }}</span>
                @endif
              </div>
              @if($r->decision_note && ! $r->auto_declined)
                <div class="rr-decision">“{{ $r->decision_note }}”</div>
              @endif
            </div>
            <span class="rr-meta">{{ ($r->decided_at ?? $r->updated_at)?->format('M j, Y') }}</span>
          </article>
        @endforeach
        @foreach($expertDecided as $r)
          @php
            $decider = trim(($r->decider->first_name ?? '').' '.($r->decider->last_name ?? ''));
            $label = ['approved' => 'Approved', 'declined' => 'Declined', 'withdrawn' => 'Closed'][$r->status] ?? ucfirst($r->status);
          @endphp
          <article class="rr-card rr-decided">
            <span class="rr-status {{ $r->status }}">{{ $label }}</span>
            <div class="rr-main">
              <div class="rr-line">Expert change: <b>{{ $r->expert?->name ?? 'Expert' }}</b> · {{ $r->team?->team_name }}</div>
              <div class="rr-meta">
                <span>{{ $expertSectionOf[$r->id] ?? '' }}</span>
                @if($decider)<span>By {{ $decider }}</span>@endif
              </div>
              @if($r->decision_note)
                <div class="rr-decision">“{{ $r->decision_note }}”</div>
              @endif
            </div>
            <span class="rr-meta">{{ ($r->decided_at ?? $r->updated_at)?->format('M j, Y') }}</span>
          </article>
        @endforeach
      </div>
      <div class="rr-empty" id="decided-empty" @if($decided->isNotEmpty() || $expertDecided->isNotEmpty()) hidden @endif>
        <b>No decisions yet</b>
        Requests you approve or decline are kept here.
      </div>
    </section>

  </div>

<div class="rr-modal" id="rr-confirm" role="dialog" aria-modal="true" aria-labelledby="rr-confirm-title">
  <div class="rr-modal-box">
    <div class="rr-modal-title" id="rr-confirm-title"></div>
    <div class="rr-modal-lede" id="rr-confirm-lede"></div>
    <ul class="rr-effects" id="rr-effects"></ul>
    <label class="rr-note-label" for="rr-note" id="rr-note-label"></label>
    <textarea class="rr-note-input" id="rr-note" maxlength="1000"></textarea>
    <div class="rr-note-hint"><span id="rr-note-hint"></span><span id="rr-note-count">0 / 1000</span></div>
    <div class="rr-error" id="rr-error" role="alert" hidden></div>
    <div class="rr-modal-actions">
      <button type="button" class="rr-btn" id="rr-cancel">Cancel</button>
      <button type="button" class="rr-btn" id="rr-submit"></button>
    </div>
  </div>
</div>

<div id="rr-toast" class="toast"></div>

<script nonce="{{ Vite::cspNonce() }}">
(function () {
  const $ = (id) => document.getElementById(id);
  const updateUrl = @json(url('capsa/leave-requests'), JSON_UNESCAPED_SLASHES);
  let current = null; // { card, decision }
  let lastFocus = null;

  // Tabs
  const tabs = [$('tab-pending'), $('tab-decided')];
  function selectTab(tab) {
    tabs.forEach((t) => {
      const on = t === tab;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      $(t.getAttribute('aria-controls')).hidden = !on;
    });
  }
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => selectTab(t));
    t.addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      const next = tabs[(i + 1) % tabs.length];
      selectTab(next); next.focus();
    });
  });

  function effectsFor(card, decision) {
    const d = card.dataset;
    if (d.type === 'expert') {
      return decision === 'declined'
        ? [{ text: d.member + ' stays the ' + d.role + ' of ' + d.team + '.' }, { text: d.requester + ' is told you declined, with your reason.' }]
        : [
            { text: d.member + ' is removed as the ' + d.role + ' of ' + d.team + ' straight away.' },
            { text: d.replacement
                ? 'Their application goes to ' + d.replacement + ' if they still have a slot. ' + d.replacement + ' accepts or declines it.'
                : 'The team can apply to another ' + d.role + '.' },
            { text: 'Every member of the team is notified.' },
          ];
    }
    const leave = d.type === 'leave';
    if (decision === 'declined') {
      return [
        { text: d.member + ' stays on ' + d.team + '.' },
        { text: (leave ? d.member : d.requester) + ' is told you declined, with your reason.' },
      ];
    }
    const items = [
      { text: d.member + ' is taken off ' + d.team + ' straight away.' },
      { text: 'The team goes from ' + d.now + ' to ' + d.after + ' members.'
          + (d.below === '1' ? ' That is below the minimum, and no one can be added to replace them.' : ''),
        warn: d.below === '1' },
      { text: d.member + ' and the rest of the team are notified.' },
    ];
    return items;
  }

  function noteRequired() { return current && current.decision === 'declined'; }

  function refreshSubmit() {
    const n = $('rr-note').value.trim().length;
    $('rr-note-count').textContent = n + ' / 1000';
    $('rr-submit').disabled = noteRequired() && n === 0;
  }

  function openConfirm(card, decision) {
    current = { card, decision };
    lastFocus = document.activeElement;
    const d = card.dataset;
    const approve = decision === 'approved';

    $('rr-confirm-title').textContent = approve
      ? (d.type === 'leave' ? 'Let ' + d.member + ' leave?' : (d.type === 'expert' ? 'Remove ' + d.member + ' as ' + d.role + '?' : 'Remove ' + d.member + '?'))
      : 'Decline this request?';
    $('rr-confirm-lede').textContent = d.type === 'expert'
      ? d.team + ' asked to change an expert who already took them on. This cannot be undone from here.'
      : 'This cannot be undone from here.';

    const list = $('rr-effects');
    list.innerHTML = '';
    effectsFor(card, decision).forEach((item) => {
      const li = document.createElement('li');
      li.textContent = item.text;
      if (item.warn) li.className = 'warn';
      list.appendChild(li);
    });

    $('rr-note-label').textContent = approve ? 'Note to the team (optional)' : 'Why are you declining?';
    $('rr-note-hint').textContent = approve ? 'Sent with the approval.' : 'Required. The team sees this.';
    $('rr-note').value = '';
    $('rr-error').hidden = true;

    const submit = $('rr-submit');
    submit.textContent = approve ? 'Approve' : 'Decline';
    submit.className = 'rr-btn ' + (approve ? 'rr-btn-primary' : 'rr-btn-danger');
    refreshSubmit();

    $('rr-confirm').classList.add('show');
    (approve ? submit : $('rr-note')).focus();
  }

  function closeConfirm() {
    $('rr-confirm').classList.remove('show');
    current = null;
    if (lastFocus) lastFocus.focus();
  }

  function toast(message, ok) {
    if (window.ctToast) { return ctToast(message, ok ? 'success' : 'error'); }
    const el = $('rr-toast');
    el.textContent = message;
    el.style.borderLeftColor = ok ? 'var(--green)' : 'var(--red)';
    el.classList.add('show');
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => el.classList.remove('show'), 4000);
  }

  function moveToDecided(card, data) {
    const d = card.dataset;
    const labels = { approved: 'Approved', declined: 'Declined', withdrawn: 'Withdrawn' };
    const row = document.createElement('article');
    row.className = 'rr-card rr-decided';

    const status = document.createElement('span');
    status.className = 'rr-status ' + data.status;
    status.textContent = labels[data.status] || data.status;

    const main = document.createElement('div');
    main.className = 'rr-main';
    const line = document.createElement('div');
    line.className = 'rr-line';
    line.append((d.type === 'leave' ? 'Leaving' : (d.type === 'expert' ? 'Expert change' : 'Removal')) + ': ');
    const b = document.createElement('b');
    b.textContent = d.member;
    line.append(b, ' · ' + d.team);
    main.appendChild(line);
    if (data.decision_note) {
      const note = document.createElement('div');
      note.className = 'rr-decision';
      note.textContent = '“' + data.decision_note + '”';
      main.appendChild(note);
    }

    const when = document.createElement('span');
    when.className = 'rr-meta';
    when.textContent = data.decided_at || '';

    row.append(status, main, when);
    $('decided-list').prepend(row);
    $('decided-empty').hidden = true;
    $('count-decided').textContent = $('decided-list').children.length;

    card.remove();
    const left = $('pending-list').children.length;
    $('count-pending').textContent = left;
    $('count-pending').classList.toggle('has', left > 0);
    $('pending-empty').hidden = left > 0;
  }

  $('pending-list').addEventListener('click', (e) => {
    const btn = e.target.closest('[data-decide]');
    if (btn) openConfirm(btn.closest('.rr-card'), btn.dataset.decide);
  });

  $('rr-note').addEventListener('input', refreshSubmit);
  $('rr-cancel').addEventListener('click', closeConfirm);
  $('rr-confirm').addEventListener('click', (e) => { if (e.target.id === 'rr-confirm') closeConfirm(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && $('rr-confirm').classList.contains('show')) closeConfirm();
  });

  $('rr-submit').addEventListener('click', () => {
    if (!current) return;
    const { card, decision } = current;
    const btn = $('rr-submit');
    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = decision === 'approved' ? 'Approving…' : 'Declining…';

    fetch(card.dataset.url || (updateUrl + '/' + card.dataset.id), {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
      body: JSON.stringify({ status: decision, note: $('rr-note').value.trim() || null }),
    })
      .then((r) => r.json().then((data) => ({ ok: r.ok, code: r.status, data })))
      .then(({ ok, code, data }) => {
        if (!ok || !data.success) {
          const msg = (data.errors && Object.values(data.errors)[0][0]) || data.message || 'Could not save the decision.';
          $('rr-error').textContent = msg;
          $('rr-error').hidden = false;
          // Already decided or no longer possible: the card is stale either way.
          if (code === 409) { card.remove(); $('count-pending').textContent = $('pending-list').children.length; }
          return;
        }
        $('rr-confirm').classList.remove('show');
        current = null;
        moveToDecided(card, data.request);
        toast(data.message, true);
      })
      .catch(() => { $('rr-error').textContent = 'Network error. Nothing was saved.'; $('rr-error').hidden = false; })
      .finally(() => { btn.textContent = label; refreshSubmit(); });
  });
})();
</script>
