'use strict';

const CUSTOMER_PORTAL_VERSION = 'aabhushan-customer-v1';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;

    const requestUrl = new URL(event.request.url);
    if (requestUrl.origin !== self.location.origin) return;

    // Customer pages contain private order data and CSRF tokens. Keep every
    // request network-only so authenticated content is never stored offline.
    event.respondWith(fetch(event.request));
});
