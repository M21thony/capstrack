<style>
  /* This partial is included on every student page via the sidebar, but
     several pages (submission, personal-info, archive, duplication,
     resources, review, profile) never define .modal-overlay/.pm-close-btn
     themselves - without a base rule here, these two overlays render as
     plain unstyled, unpositioned, always-visible boxes stacked in normal
     page flow instead of a centered fixed backdrop. Additive only: on
     pages that already define .modal-overlay for their own modals, this
     just adds the opacity/pointer-events toggle their JS doesn't already
     provide - it never overrides their base display/background rules. */
  .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:1000;opacity:0;pointer-events:none;transition:opacity .2s}
  .modal-overlay.active{opacity:1;pointer-events:auto}
  .pm-close-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0;background-color:var(--bg2);color:var(--text3);border:none;border-radius:50%;cursor:pointer;transition:all .15s ease-in-out;outline:none}
  .pm-close-btn:hover{background-color:var(--border);color:var(--text);transform:scale(1.05)}

  #jrp-overlay{background:rgba(24,25,31,0.5);backdrop-filter:blur(3px);cursor:default}
  /* the card sits above the overlay, so the close button needs to sit above the card */
  #jrp-overlay .pm-close-btn{z-index:100;position:relative}
  #jrp-overlay .card{padding:0!important;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.18),0 8px 24px rgba(0,0,0,0.12);overflow:hidden;display:flex;flex-direction:column;animation:jrpIn 0.2s ease forwards}
  @keyframes jrpIn{from{opacity:0;transform:translateY(10px) scale(0.985)}to{opacity:1;transform:translateY(0) scale(1)}}

  #jrp-header{display:flex;align-items:center;gap:14px;padding:20px 56px 16px 24px;border-bottom:1px solid var(--border)}
  #jrp-header-icon{width:42px;height:42px;border-radius:12px;background:var(--bg3);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--text2);flex-shrink:0}
  #jrp-header-text{flex:1;min-width:0}
  #jrp-title{font-size:15px;font-weight:700;color:var(--text);letter-spacing:-0.2px}
  #jrp-subtitle{font-size:12px;color:var(--text3);margin-top:2px;line-height:1.45}
  #jrp-count{flex-shrink:0;min-width:24px;height:24px;border-radius:12px;background:var(--text3);color:#fff;font-size:11px;font-weight:700;display:none;align-items:center;justify-content:center;padding:0 8px}

  #jrp-body{padding:18px 24px 24px}
  #jrp-list{display:none;flex-direction:column;gap:10px;max-height:calc(100dvh - 260px);overflow-y:auto;scrollbar-width:thin}
  #jrp-list::-webkit-scrollbar{width:5px}
  #jrp-list::-webkit-scrollbar-thumb{background:var(--border2);border-radius:6px}
  #jrp-list::-webkit-scrollbar-track{background:transparent}

  .jrp-item{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--bg2);transition:border-color 0.15s,box-shadow 0.15s}
  .jrp-item:hover{border-color:var(--border2);box-shadow:0 3px 10px rgba(0,0,0,0.05)}
  .jrp-item-avatar{width:44px;height:44px;border-radius:50%;object-fit:cover;flex-shrink:0}
  .jrp-item-avatar-fallback{width:44px;height:44px;border-radius:50%;background:var(--purple-bg);color:var(--purple);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0;border:1px solid rgba(124,58,237,0.15)}
  .jrp-item-info{flex:1;min-width:0}
  .jrp-item-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .jrp-item-sub{font-size:11.5px;color:var(--text2);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
  .jrp-item-sub strong{color:var(--text);font-weight:600}
  .jrp-item-time{display:inline-flex;align-items:center;gap:4px;font-size:10.5px;color:var(--text3);margin-top:4px}
  .jrp-actions{display:flex;gap:6px;flex-shrink:0}
  .jrp-btn{padding:7px 12px;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;transition:all 0.15s ease;white-space:nowrap}
  .jrp-btn-accept{background:var(--green);color:#fff;border:1px solid var(--green)}
  .jrp-btn-accept:hover{background:#15803d;border-color:#15803d;box-shadow:0 3px 8px rgba(22,163,74,0.3)}
  .jrp-btn-decline{background:var(--red-bg);color:var(--red);border:1px solid rgba(220,38,38,0.22)}
  .jrp-btn-decline:hover{background:var(--red);color:#fff;border-color:var(--red)}
  .jrp-btn-later{background:var(--bg3);color:var(--text2);border:1px solid var(--border)}
  .jrp-btn-later:hover{background:var(--bg4);color:var(--text)}

  #jrp-empty{display:none;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:36px 0 24px;color:var(--text3)}
  #jrp-empty svg{opacity:0.35;margin-bottom:12px}
  #jrp-empty-title{font-size:13px;font-weight:600;color:var(--text2)}
  #jrp-empty-sub{font-size:12px;margin-top:4px}

  #jrp-confirm-overlay{background:rgba(24,25,31,0.5);backdrop-filter:blur(3px);cursor:default;z-index:10000}
  #jrp-confirm-overlay .card{padding:22px!important;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,0.18),0 8px 24px rgba(0,0,0,0.12);display:flex;flex-direction:column;animation:jrpIn 0.2s ease forwards}
  #jrp-confirm-icon{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-bottom:14px}
  #jrp-confirm-icon.accept{background:var(--green-bg);color:var(--green)}
  #jrp-confirm-icon.decline{background:var(--red-bg);color:var(--red)}
  #jrp-confirm-title{font-size:16px;font-weight:700;color:var(--text);letter-spacing:-0.2px}
  #jrp-confirm-sub{font-size:13px;color:var(--text2);margin-top:8px;line-height:1.55}
  #jrp-confirm-sub strong{color:var(--text);font-weight:600}
  #jrp-confirm-actions{display:flex;gap:8px;margin-top:22px}
  #jrp-confirm-actions .jrp-btn{flex:1;padding:9px 12px;font-size:12px;text-align:center}
  #jrp-confirm-actions .jrp-btn-cancel{background:var(--bg3);color:var(--text2);border:1px solid var(--border)}
  #jrp-confirm-actions .jrp-btn-cancel:hover{background:var(--bg4);color:var(--text)}

  @media (max-width:768px){
    #jrp-overlay .card{border-radius:20px 20px 0 0}
  }
  @media (max-width:480px){
    #jrp-header{padding:16px 46px 14px 16px}
    #jrp-header-icon{width:38px;height:38px;border-radius:10px}
    #jrp-title{font-size:14px}
    #jrp-body{padding:14px 16px 18px}
    .jrp-item{flex-wrap:wrap;padding:12px}
    .jrp-actions{width:100%}
    .jrp-btn{flex:1}
  }
</style>
<div class="modal-overlay" id="jrp-overlay" onclick="if(event.target===this)jrpClose()">
  <div class="card modal-card" style="width: 480px; max-width: 92vw; position: relative;">
    <button type="button" onclick="event.stopPropagation(); jrpClose()" class="pm-close-btn" aria-label="Close" style="position: absolute; top: 18px; right: 18px; z-index: 1000; pointer-events: auto;">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div id="jrp-header">
      <div id="jrp-header-icon">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
      </div>
      <div id="jrp-header-text">
        <div id="jrp-title">Incoming Requests</div>
        <div id="jrp-subtitle">People who want to join your team or invited you to theirs.</div>
      </div>
      <div id="jrp-count">0</div>
    </div>
    <div id="jrp-body">
      <div id="jrp-list"></div>
      <div id="jrp-empty">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
        <div id="jrp-empty-title">No pending requests</div>
        <div id="jrp-empty-sub">New requests will appear here.</div>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="jrp-confirm-overlay" onclick="if(event.target===this)jrpConfirmClose()">
  <div class="card modal-card" style="width: 420px; max-width: 92vw; position: relative;">
    <div id="jrp-confirm-icon">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
    </div>
    <div id="jrp-confirm-title"></div>
    <div id="jrp-confirm-sub"></div>
    <div id="jrp-confirm-actions">
      <button type="button" class="jrp-btn jrp-btn-cancel" onclick="jrpConfirmClose()">No</button>
      <button type="button" class="jrp-btn" id="jrp-confirm-ok"></button>
    </div>
  </div>
</div>

<script>
(function () {
  var overlay = document.getElementById('jrp-overlay');
  if (!overlay) return;

  var updateUrl = '{{ route('invitations.update', '__ID__') }}';
  var remindUrl = '{{ route('invitations.remind-later', '__ID__') }}';
  var itemsById = {};

  function jrpBuildList(items) {
    var list = document.getElementById('jrp-list');
    var empty = document.getElementById('jrp-empty');
    var count = document.getElementById('jrp-count');
    list.innerHTML = '';
    itemsById = {};

    count.textContent = items.length;
    count.style.display = items.length ? 'inline-flex' : 'none';

    if (!items.length) {
      list.style.display = 'none';
      empty.style.display = 'flex';
      return;
    }

    list.style.display = 'flex';
    empty.style.display = 'none';

    items.forEach(function (item) {
      itemsById[item.id] = item;
      var isRequest = item.type === 'request';
      var avatar = item.avatar
        ? '<img src="'+item.avatar+'" alt="" class="jrp-item-avatar">'
        : '<div class="jrp-item-avatar-fallback">'+item.name.charAt(0).toUpperCase()+'</div>';

      var div = document.createElement('div');
      div.className = 'jrp-item';
      div.innerHTML = avatar +
        '<div class="jrp-item-info">' +
          '<div class="jrp-item-name">'+item.name+'</div>' +
          '<div class="jrp-item-sub">'+(isRequest ? 'wants to join <strong>'+item.team_name+'</strong>' : 'invited you to join <strong>'+item.team_name+'</strong>')+'</div>' +
          '<div class="jrp-item-time"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'+item.time_ago+'</div>' +
        '</div>' +
        '<div class="jrp-actions">' +
          '<button type="button" class="jrp-btn jrp-btn-accept" data-act="accept" data-id="'+item.id+'">Accept</button>' +
          '<button type="button" class="jrp-btn jrp-btn-decline" data-act="decline" data-id="'+item.id+'">Decline</button>' +
          '<button type="button" class="jrp-btn jrp-btn-later" data-act="remind" data-id="'+item.id+'">Later</button>' +
        '</div>';
      list.appendChild(div);
    });
  }

  function jrpSubmit(url, status) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = url;

    // A form can only send GET or POST, so the real verb rides along in
    // _method the way a Blade method directive does it. Both of these need
    // one: accepting and declining are a PATCH, and Later is a PUT. Later
    // used to send a bare POST, which the router answered with
    // MethodNotAllowed - so clicking it crashed the page.
    var fields = status
      ? [['_method', 'PATCH'], ['status', status]]
      : [['_method', 'PUT']];

    fields.forEach(function (pair) {
      var f = document.createElement('input');
      f.type = 'hidden';
      f.name = pair[0];
      f.value = pair[1];
      form.appendChild(f);
    });
    var csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    var meta = document.querySelector('meta[name="csrf-token"]');
    csrf.value = meta ? meta.getAttribute('content') : '{{ csrf_token() }}';
    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
  }

  function jrpOpen(items) {
    jrpBuildList(items);
    overlay.classList.add('active');
  }

  function jrpClose() {
    // Dismissing is "remind me later" for everything in the popup. Without
    // this it reopened on the next page load, so closing it did nothing.
    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : '{{ csrf_token() }}';

    var pending = Object.keys(itemsById).map(function (id) {
      return fetch(remindUrl.replace('__ID__', id), {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': token
        }
      });
    });

    Promise.all(pending).finally(function () {
      overlay.classList.remove('active');
    });
  }

  var confirmOverlay = document.getElementById('jrp-confirm-overlay');
  var confirmIcon = document.getElementById('jrp-confirm-icon');
  var confirmTitle = document.getElementById('jrp-confirm-title');
  var confirmSub = document.getElementById('jrp-confirm-sub');
  var confirmOk = document.getElementById('jrp-confirm-ok');
  var confirmUrl = null;
  var confirmStatus = null;

  function jrpConfirmClose() {
    confirmOverlay.classList.remove('active');
    confirmUrl = null;
    confirmStatus = null;
  }
  window.jrpConfirmClose = jrpConfirmClose;

  function jrpConfirmShow(act, id, item) {
    var isRequest = item.type === 'request';
    var name = item.name;
    var team = item.team_name || 'the team';

    if (act === 'accept') {
      confirmUrl = updateUrl.replace('__ID__', id);
      confirmStatus = 'accepted';
      confirmIcon.className = 'accept';
      // A request to join and an invitation to you are different things, so
      // the title says which one is being answered.
      confirmTitle.textContent = isRequest ? 'Accept Request' : 'Accept Invitation';
      confirmSub.innerHTML = 'You are about to accept <strong>'+name+'</strong>\'s '+
        (isRequest ? 'request to join <strong>'+team+'</strong>' : 'invitation to <strong>'+team+'</strong>')+
        '. They will be added as a member of your team.';
      confirmOk.textContent = 'Yes';
      confirmOk.className = 'jrp-btn jrp-btn-accept';
    } else {
      confirmUrl = updateUrl.replace('__ID__', id);
      confirmStatus = 'declined';
      confirmIcon.className = 'decline';
      confirmTitle.textContent = 'Decline';
      confirmSub.innerHTML = 'You are about to decline <strong>'+name+'</strong>\'s '+
        (isRequest ? 'request to join <strong>'+team+'</strong>' : 'invitation to <strong>'+team+'</strong>')+
        '. They will be notified that you declined.';
      confirmOk.textContent = 'Yes';
      confirmOk.className = 'jrp-btn jrp-btn-decline';
    }

    confirmOverlay.classList.add('active');
  }

  confirmOk.addEventListener('click', function () {
    if (confirmUrl) jrpSubmit(confirmUrl, confirmStatus);
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-act]');
    if (!btn) return;
    var id = btn.getAttribute('data-id');
    var act = btn.getAttribute('data-act');
    var item = itemsById[id];
    if (act === 'accept' || act === 'decline') {
      if (item) jrpConfirmShow(act, id, item);
      return;
    }
    if (act === 'remind') jrpSubmit(remindUrl.replace('__ID__', id));
  });

  fetch('{{ route('invitations.popup') }}', {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var items = (data && data.items) || [];
      if (items.length) jrpOpen(items);
    })
    .catch(function () {});
})();
</script>
