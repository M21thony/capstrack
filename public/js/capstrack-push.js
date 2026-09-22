/*
  Turns a browser into one CapsTrack can push notifications to.

  Everything it needs is on the bell itself (data-vapid, data-subscribe,
  data-csrf) rather than in meta tags, so the bell partial is the only file
  that has to carry it and the twenty-eight pages that include it need no edit.

  Nothing here is required for CapsTrack to work. On a browser that has said
  No, or an old one, or a plain http:// address where the browser forbids all
  of this, every function below quietly does nothing and the bell carries on.
*/
(function () {
  'use strict';

  var wrap = document.getElementById('notif-wrap');
  var vapid = wrap && wrap.dataset.vapid;

  // isSecureContext is the honest test. Push needs https://, except on
  // localhost, which browsers treat as safe so it works while developing.
  var supported = !!(
    wrap &&
    vapid &&
    window.isSecureContext &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window
  );

  function urlBase64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = window.atob(base64);
    var output = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; ++i) {
      output[i] = raw.charCodeAt(i);
    }
    return output;
  }

  function sendToServer(subscription) {
    var json = subscription.toJSON();

    return fetch(wrap.dataset.subscribe, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': wrap.dataset.csrf },
      body: JSON.stringify({
        endpoint: json.endpoint,
        p256dh: json.keys.p256dh,
        auth: json.keys.auth,
      }),
    });
  }

  function subscribe() {
    return navigator.serviceWorker
      .register('/sw.js')
      .then(function () {
        return navigator.serviceWorker.ready;
      })
      .then(function (registration) {
        return registration.pushManager.getSubscription().then(function (existing) {
          if (existing) {
            return existing;
          }

          return registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapid),
          });
        });
      })
      .then(function (subscription) {
        // The server stores one row per browser keyed on the endpoint, so
        // re-sending the same one is harmless - but there is no reason to do
        // it on all twenty-eight pages, only once per visit.
        if (sessionStorage.getItem('capstrack-push-sent') === subscription.endpoint) {
          return subscription;
        }

        return sendToServer(subscription).then(function () {
          try {
            sessionStorage.setItem('capstrack-push-sent', subscription.endpoint);
          } catch (e) {
            /* private window - re-sending next page load costs nothing */
          }
          return subscription;
        });
      });
  }

  window.CapsTrackPush = {
    supported: supported,

    /** 'unsupported' | 'default' | 'granted' | 'denied' */
    state: function () {
      return supported ? Notification.permission : 'unsupported';
    },

    /** Ask the browser for permission, then register this browser. */
    enable: function () {
      if (!supported) {
        return Promise.resolve('unsupported');
      }

      return Notification.requestPermission().then(function (permission) {
        if (permission !== 'granted') {
          return permission;
        }

        return subscribe().then(function () {
          return 'granted';
        });
      });
    },
  };

  // Somebody who has already allowed notifications should not have to ask
  // again on a new machine session - re-register quietly on page load.
  if (supported && Notification.permission === 'granted') {
    subscribe().catch(function (e) {
      console.warn('CapsTrack push could not register:', e && e.message);
    });
  }
})();
