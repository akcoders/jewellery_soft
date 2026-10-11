const CACHE = 'aabhushan-intelligence-v1-20261011';
const SHELL = ['./', './index.html', './app.css?v=1', './app.js?v=1', './vendor/chart.min.js', './manifest.json', '../pwa/icons/Icon-192.png', '../pwa/icons/Icon-512.png', '../pwa/fonts/manrope/Manrope.ttf'];
self.addEventListener('install', event => event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SHELL)).then(() => self.skipWaiting())));
self.addEventListener('activate', event => event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('aabhushan-intelligence-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim())));
self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET' || new URL(event.request.url).pathname.startsWith('/api/')) return;
  event.respondWith(fetch(event.request).then(response => { if (response.ok) caches.open(CACHE).then(cache => cache.put(event.request, response.clone())); return response; }).catch(() => caches.match(event.request).then(hit => hit || caches.match('./index.html'))));
});
