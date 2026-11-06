'use strict';

self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keys = await caches.keys();
    await Promise.all(keys.filter((key) => key.startsWith('aabhushan-'))
      .map((key) => caches.delete(key)));
    await self.clients.claim();
    // Unregister this emergency worker so the normal PWA can install again
    // after resume. Existing windows navigate to the server suspension page.
    await self.registration.unregister();
    const windows = await self.clients.matchAll({type: 'window'});
    await Promise.all(windows.map((client) =>
      client.navigate(client.url).catch(() => undefined)));
  })());
});

self.addEventListener('fetch', (event) => {
  if (new URL(event.request.url).origin === self.location.origin) {
    event.respondWith(fetch(event.request, {cache: 'no-store'}));
  }
});
