const CACHE_NAME = 'mbpos-v5-shell-2026-09-17-r7';
const APP_SHELL = [
  './offline.html',
  './assets/css/style.css',
  './assets/js/main.js',
  './manifest.webmanifest'
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(
      keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
    ))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;

  const request = event.request;
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => response)
        // Authenticated PHP pages are never cached. Offline navigation always
        // receives a neutral shell rather than stale operational records.
        .catch(() => caches.match('./offline.html'))
    );
    return;
  }

  const url = new URL(request.url);
  const isSameOrigin = url.origin === self.location.origin;
  const isStaticAsset = isSameOrigin && (
    url.pathname.includes('/assets/') ||
    url.pathname.endsWith('/bg.jpg') ||
    url.pathname.endsWith('/offline.html') ||
    url.pathname.endsWith('/manifest.webmanifest')
  );

  // API, PHP, and other dynamic GET responses may contain authenticated data.
  // They always go directly to the network and are never written to Cache API.
  if (!isStaticAsset) {
    event.respondWith(fetch(request));
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      const refresh = fetch(request).then((response) => {
        if (response.ok) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        }
        return response;
      }).catch(() => cached);
      return cached || refresh;
    })
  );
});
