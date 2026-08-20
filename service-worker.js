// service-worker.js – complete service worker with push, badge, notification click

self.addEventListener('install', (event) => {
  console.log('Service Worker installed');
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  console.log('Service Worker activated');
  event.waitUntil(clients.claim());
});

// Push notification listener
self.addEventListener('push', function(event) {
  let data = { title: 'New message', body: 'You have a new message', data: {} };

  if (event.data) {
    try { data = event.data.json(); } catch (e) { data.body = event.data.text(); }
  }

  const options = {
    body: data.body,
    icon: '/img/logo.png',
    badge: '/img/logo.png',
    vibrate: [200, 100, 200],
    data: data.data || {},
    tag: 'chat_message_' + Date.now(),
    renotify: true,
    requireInteraction: true
  };

  const badgeCount = data.data && data.data.badge_count ? data.data.badge_count : 1;

  event.waitUntil(
    (async function() {
      // Set badge
      if ('setAppBadge' in self.registration) {
        await self.registration.setAppBadge(badgeCount).catch(() => {});
      }
      return self.registration.showNotification(data.title, options);
    })()
  );
});

// Notification click handler
self.addEventListener('notificationclick', function(event) {
  event.notification.close();

  const chatId = event.notification.data && event.notification.data.chat_id
    ? event.notification.data.chat_id
    : null;

  const urlToOpen = chatId ? `/chat.php?open_chat=${chatId}` : '/chat.php';

  event.waitUntil(
    (async function() {
      if ('clearAppBadge' in self.registration) {
        await self.registration.clearAppBadge().catch(() => {});
      }

      const windowClients = await clients.matchAll({ type: 'window', includeUncontrolled: true });
      for (let client of windowClients) {
        if (client.url.includes(urlToOpen) && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })()
  );
});

// Optional: handle fetch events (simple network-first)
self.addEventListener('fetch', (event) => {
  // Add custom caching logic here if needed
});