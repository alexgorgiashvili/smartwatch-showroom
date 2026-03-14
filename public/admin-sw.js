self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(clients.claim());
});

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
    (async () => {
      const resolvedUrl = new URL(targetUrl, self.location.origin);
      const normalizedTargetUrl = new URL(
        `${resolvedUrl.pathname}${resolvedUrl.search}${resolvedUrl.hash}`,
        self.location.origin
      ).href;
      const windowClients = await clients.matchAll({ type: 'window' });

      for (const client of windowClients) {
        if (typeof client.focus !== 'function') {
          continue;
        }

        const isAdminClient = client.url.startsWith(`${self.location.origin}/admin`);
        if (!isAdminClient) {
          continue;
        }

        if (client.url !== normalizedTargetUrl && 'navigate' in client) {
          await client.navigate(normalizedTargetUrl);
        }

        return client.focus();
      }

      if (clients.openWindow) {
        return clients.openWindow(normalizedTargetUrl);
      }
    })().catch(() => {
      if (clients.openWindow) {
        const fallbackUrl = new URL(targetUrl, self.location.origin);
        const normalizedFallbackUrl = new URL(
          `${fallbackUrl.pathname}${fallbackUrl.search}${fallbackUrl.hash}`,
          self.location.origin
        ).href;

        return clients.openWindow(normalizedFallbackUrl);
      }
    })
  );
});
