{{--
  The notification switches, for a student, an expert or a CaPSA/TSA alike.

  One partial rather than three copies. It works out who is logged in and
  where to post by itself, so a profile page only has to @include it - the
  same way partials/notification-bell.blade.php works.

  Every switch is on a row of its own, all visible at once. Nothing folds
  open, which is how the rest of CapsTrack behaves.
--}}
@php
  $prefsPerson = auth('expert')->user() ?? auth()->user();
@endphp

@if ($prefsPerson)
  {{-- Ahead of the card, so it is never on screen without its styles:
       after it, the card was drawn plain first and grew into them. --}}
  <style nonce="{{ Vite::cspNonce() }}">
  .notif-prefs{max-width:560px;background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,0.05);font-family:'DM Sans','DM Sans Fallback',sans-serif}
  .notif-prefs-title{font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.8px;margin-bottom:18px}
  .notif-prefs-btn{padding:8px 16px;border:none;border-radius:8px;background:#2563eb;color:#fff;font-size:12px;font-weight:700;cursor:pointer;font-family:'DM Sans','DM Sans Fallback',sans-serif}
  .notif-prefs-btn:hover{background:#1d4ed8}
  .notif-prefs-btn:disabled{opacity:.6;cursor:default}
  .notif-prefs-intro{font-size:12.5px;color:var(--text3);line-height:1.5;margin-bottom:14px}
  .notif-prefs-perm{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:10px 12px;border-radius:8px;background:rgba(37,99,235,0.06);font-size:12px;color:var(--text2);line-height:1.45;margin-bottom:14px}
  #notif-prefs-allow{padding:6px 12px;font-size:11.5px;flex-shrink:0}
  .notif-prefs-head{display:grid;grid-template-columns:1fr 72px 72px;gap:8px;padding-bottom:6px;font-size:10.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--text3);text-align:center;border-bottom:1px solid var(--border)}
  .notif-prefs-head span:first-child{text-align:left}
  .notif-prefs-row{display:grid;grid-template-columns:1fr 72px 72px;gap:8px;align-items:center;padding:11px 0;border-bottom:1px solid var(--border)}
  .notif-prefs-row:last-of-type{border-bottom:none}
  .notif-prefs-master{grid-template-columns:1fr auto;margin-bottom:8px;cursor:pointer}
  .notif-prefs-master .notif-prefs-label{font-weight:600;color:var(--text)}
  .notif-prefs-label{font-size:12.5px;color:var(--text2);line-height:1.4}
  .notif-prefs-cell{text-align:center}
  .notif-prefs-none{color:var(--text3);opacity:.5}
  .notif-prefs input[type=checkbox]{width:16px;height:16px;accent-color:#2563eb;cursor:pointer}
  .notif-prefs.is-off .notif-prefs-head,
  .notif-prefs.is-off .notif-prefs-row:not(.notif-prefs-master){opacity:.4;pointer-events:none}
  .notif-prefs-foot{display:flex;align-items:center;justify-content:flex-end;gap:12px;margin-top:16px}
  .notif-prefs-saved{font-size:11.5px;color:var(--text3)}
  </style>
  @php
    $prefsAudience = $prefsPerson->notificationAudience();
    $prefsTypes = \App\Support\NotificationTypes::for($prefsAudience);
    $prefsSaved = $prefsPerson->notification_prefs ?? [];
    $prefsAll = ($prefsSaved['all'] ?? true) !== false;
    $prefsUrl = $prefsAudience === 'expert'
        ? route('instructor.notifications.preferences')
        : route('notifications.preferences');
  @endphp

  {{-- The box draws itself rather than borrowing the .card class: the student
       side loads a different stylesheet that has no .card, so a shared card
       would have come out unstyled on one of the three pages. --}}
  <div class="notif-prefs" id="notifications"
       data-url="{{ $prefsUrl }}" data-csrf="{{ csrf_token() }}">
    <div class="notif-prefs-title">Notifications</div>

    <p class="notif-prefs-intro">
      Desktop notifications pop up in the corner of your screen even when CapsTrack
      is not the window you are looking at. Important notifications are also sent to your
      Google email; switch off any you do not want in the Email column.
    </p>

    {{-- What the browser itself allows. Nothing below matters if it says no. --}}
    <div class="notif-prefs-perm" id="notif-prefs-perm">
      <span id="notif-prefs-perm-text"></span>
      <button type="button" class="notif-prefs-btn" id="notif-prefs-allow" style="display:none">Allow notifications</button>
    </div>
    <script nonce="{{ Vite::cspNonce() }}">
    // Said as the box is drawn, not once the page has loaded: filled in then,
    // the box grew in and pushed every switch below it down. The same test
    // /js/capstrack-push.js makes, which is not loaded yet at this point.
    (function () {
      var wrap = document.getElementById('notif-wrap');
      var supported = !!(wrap && wrap.dataset.vapid && window.isSecureContext
        && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window);
      var state = supported ? Notification.permission : 'unsupported';
      var text = document.getElementById('notif-prefs-perm-text');
      if (state === 'granted') {
        text.textContent = 'This browser is set up for desktop notifications.';
      } else if (state === 'denied') {
        text.textContent = 'This browser is blocking notifications. Turn them back on in its site settings for CapsTrack.';
      } else if (state === 'default') {
        text.textContent = 'This browser has not been set up for desktop notifications yet.';
        document.getElementById('notif-prefs-allow').style.display = 'inline-flex';
      } else {
        text.textContent = 'This browser cannot show desktop notifications. CapsTrack has to be opened over https:// for them to work.';
      }
    })();
    </script>

    <label class="notif-prefs-row notif-prefs-master">
      <span class="notif-prefs-label">Notify me at all</span>
      <input type="checkbox" id="notif-prefs-all" {{ $prefsAll ? 'checked' : '' }}>
    </label>

    <div class="notif-prefs-head">
      <span></span>
      <span>Desktop</span>
      <span>Email</span>
    </div>

    @foreach ($prefsTypes as $prefsType => $prefsMeta)
      <div class="notif-prefs-row">
        <span class="notif-prefs-label">{{ $prefsMeta['label'] }}</span>
        <span class="notif-prefs-cell">
          @if ($prefsMeta['web'] ?? true)
            <input type="checkbox" data-key="web.{{ $prefsType }}"
                   {{ ($prefsSaved['web.'.$prefsType] ?? true) !== false ? 'checked' : '' }}>
          @else
            {{-- Written straight to the bell, so no desktop popup exists to switch. --}}
            <span class="notif-prefs-none" title="Shows in the bell only">&mdash;</span>
          @endif
        </span>
        <span class="notif-prefs-cell">
          @if ($prefsMeta['email'])
            <input type="checkbox" data-key="email.{{ $prefsType }}"
                   {{ ($prefsSaved['email.'.$prefsType] ?? true) !== false ? 'checked' : '' }}>
          @else
            {{-- No email has ever been sent for this one, so there is nothing
                 here to switch off. --}}
            <span class="notif-prefs-none" title="No email is sent for this">&mdash;</span>
          @endif
        </span>
      </div>
    @endforeach

    <div class="notif-prefs-foot">
      <span class="notif-prefs-saved" id="notif-prefs-saved"></span>
      <button type="button" class="notif-prefs-btn" id="notif-prefs-save">Save</button>
    </div>
  </div>


  <script src="/js/capstrack-push.js" defer></script>
  <script nonce="{{ Vite::cspNonce() }}">
  document.addEventListener('DOMContentLoaded', function () {
    var card = document.querySelector('.notif-prefs');
    if (!card) return;

    var all = document.getElementById('notif-prefs-all');
    var save = document.getElementById('notif-prefs-save');
    var saved = document.getElementById('notif-prefs-saved');

    // Turning the top switch off greys the rest out instead of hiding it, so
    // nobody wonders where their settings went.
    function paint() { card.classList.toggle('is-off', !all.checked); }
    all.addEventListener('change', paint);
    paint();

    save.addEventListener('click', function () {
      var on = {};
      card.querySelectorAll('input[data-key]').forEach(function (box) {
        on[box.getAttribute('data-key')] = box.checked;
      });

      save.disabled = true;
      saved.textContent = 'Saving...';

      fetch(card.dataset.url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': card.dataset.csrf },
        body: JSON.stringify({ all: all.checked, on: on })
      }).then(function (r) { return r.json(); }).then(function (d) {
        saved.textContent = d.success ? 'Saved.' : 'Could not save.';
      }).catch(function () {
        saved.textContent = 'Could not save.';
      }).then(function () {
        save.disabled = false;
        setTimeout(function () { saved.textContent = ''; }, 2500);
      });
    });

    var text = document.getElementById('notif-prefs-perm-text');
    var allow = document.getElementById('notif-prefs-allow');
    var ready = 'This browser is set up for desktop notifications.';
    var blocked = 'This browser is blocking notifications. Turn them back on in its site settings for CapsTrack.';

    allow.addEventListener('click', function () {
      allow.disabled = true;
      CapsTrackPush.enable().then(function (permission) {
        allow.style.display = 'none';
        text.textContent = permission === 'granted' ? ready : blocked;
      });
    });
  });
  </script>
@endif
