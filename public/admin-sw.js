self.addEventListener('push', (event) => {
  if (!event.data) {
    return;
  }

  let payload = {};
  try {
    payload = event.data.json();
  } catch (error) {
    payload = {
      title: 'New Message',
      body: event.data.text(),
      url: '/admin/inbox'
    };
  }

  const title = payload.title || 'New Message';
  const body = payload.body || 'You have a new inbox message.';
  const url = payload.url || '/admin/inbox';

  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon: '/assets/images/favicon.png',
      badge: '/assets/images/favicon.png',
      data: { url }
    })
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const targetUrl = event.notification.data?.url || '/admin/inbox';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (const client of windowClients) {
        if ('focus' in client) {
          client.navigate(targetUrl);
          return client.focus();
        }
      }

      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
