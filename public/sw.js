/*
  CapsTrack's background worker.

  The browser keeps this running on its own, with no CapsTrack tab open, which
  is the whole point: a notification reaches an expert whether or not they
  happen to have the site up. It does two things and nothing else - draw the
  notification when one arrives, and open the right page when it is clicked.

  Served from the site root on purpose. A worker can only control pages at or
  below its own folder, so /js/sw.js could never cover /instructor/*.
*/

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch (e) {
    data = { title: 'CapsTrack', body: event.data ? event.data.text() : '' };
  }

  event.waitUntil(
    self.registration.showNotification(data.title || 'CapsTrack', {
      body: data.body || '',
      icon: '/images/brand/capstrack-mark-192.png',
      badge: '/images/brand/capstrack-mark-192.png',
      // Two notifications sharing a tag replace one another rather than
      // stacking, so five documents in a row is not five pop-ups.
      tag: data.tag || 'capstrack',
      data: { url: data.url || '/', id: data.id || null },
      requireInteraction: false,
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const target = (event.notification.data && event.notification.data.url) || '/';

  event.waitUntil(
    self.clients
      .matchAll({ type: 'window', includeUncontrolled: true })
      .then((windows) => {
        // Reuse a CapsTrack tab if one is already open rather than piling up
        // a new window every time somebody clicks a notification.
        for (const client of windows) {
          if (client.url.indexOf(self.registration.scope) === 0 && 'focus' in client) {
            return client.focus().then((focused) => {
              if ('navigate' in focused) {
                return focused.navigate(target);
              }
            });
          }
        }

        return self.clients.openWindow(target);
      })
  );
});
