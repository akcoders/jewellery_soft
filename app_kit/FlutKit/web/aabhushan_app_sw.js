"use strict";

const CACHE_NAME = "aabhushan-pwa-__PWA_CACHE_VERSION__";
const APP_SHELL = [
  "./",
  "./index.html",
  "./flutter_bootstrap.js",
  "./main.dart.js",
  "./manifest.json",
  "./favicon.png",
  "./icons/Icon-192.png",
  "./icons/Icon-512.png",
  "./icons/Icon-maskable-192.png",
  "./icons/Icon-maskable-512.png",
];

self.addEventListener("install", function (event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function (cache) {
      return cache.addAll(APP_SHELL);
    })
  );
  self.skipWaiting();
});

self.addEventListener("activate", function (event) {
  event.waitUntil(
    caches
      .keys()
      .then(function (keys) {
        return Promise.all(
          keys
            .filter(function (key) {
              return key.startsWith("aabhushan-pwa-") && key !== CACHE_NAME;
            })
            .map(function (key) {
              return caches.delete(key);
            })
        );
      })
      .then(function () {
        return self.clients.claim();
      })
  );
});

function isStaticAsset(request, url) {
  if (!url.pathname.startsWith(new URL(self.registration.scope).pathname)) {
    return false;
  }
  return (
    ["script", "style", "font", "image"].includes(request.destination) ||
    /\.(?:js|css|wasm|json|png|jpe?g|webp|svg|ico|woff2?|ttf)$/i.test(
      url.pathname
    )
  );
}

self.addEventListener("fetch", function (event) {
  const request = event.request;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request)
        .then(function (response) {
          return response;
        })
        .catch(function () {
          return caches.match("./index.html");
        })
    );
    return;
  }

  if (!isStaticAsset(request, url)) return;
  event.respondWith(
    caches.match(request).then(function (cached) {
      if (cached) return cached;
      return fetch(request).then(function (response) {
        if (response && response.ok) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then(function (cache) {
            cache.put(request, copy);
          });
        }
        return response;
      });
    })
  );
});
